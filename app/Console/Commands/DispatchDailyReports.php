<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Store;
use App\Jobs\SendStoreDailyReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchDailyReports extends Command
{
    protected $signature = 'reports:dispatch-daily';
    protected $description = 'Dispatches daily report jobs for all active stores (status=1) based on their closing times.';

    public function handle(): void
    {
        $this->info('Starting dispatch of daily reports...');
        Log::info('Scheduler: DispatchDailyReports command initiated.');

        // Fetch stores that are active (status = 1) and configured for daily summaries
        $stores = Store::with('owner')
                       ->where('daily_summary', true)
                       ->where('status', 1) // Only for stores with status = 1
                       ->get();

        // The report is always for the *previous day's* business activity.
        $reportDate = Carbon::yesterday();

        foreach ($stores as $store) {
            // Pre-flight check: ensure owner exists and has an email
            if (!$store->owner || empty($store->owner->email)) {
                $this->warn("Skipping store '{$store->name}' (ID: {$store->id}): No owner associated or owner email is missing.");
                Log::warning("No owner or owner email missing for store '{$store->name}' (ID: {$store->id}). Daily report not dispatched.");
                continue;
            }

            // Get the store's closing time (e.g., 18:00:00).
            // $store->closing_time is a Carbon instance due to model casting.
            $closingTime = $store->closing_time;

            // Calculate the exact point in time when the store closed on the report day (yesterday).
            $exactClosingTimeOnReportDate = $reportDate->copy()->setTime(
                $closingTime->hour,
                $closingTime->minute,
                $closingTime->second
            );

            // Calculate the desired send time: 5 minutes after closing on the report day.
            $desiredSendTime = $exactClosingTimeOnReportDate->addMinutes(5);

            // Since this command runs at 00:00 (midnight) today, the 'desiredSendTime' (yesterday's closing + 5 mins)
            // will already be in the past. Therefore, we dispatch the job for immediate processing by the queue worker.
            SendStoreDailyReport::dispatch($store, $reportDate);

            $this->info("Dispatched report for '{$store->name}' (ID: {$store->id}) for {$reportDate->format('Y-m-d')}. Target send time was {$desiredSendTime->format('Y-m-d H:i:s')}, processing immediately.");
            Log::info("Report for store '{$store->name}' (ID: {$store->id}) for {$reportDate->format('Y-m-d')} dispatched to queue for immediate processing.");
        }

        $this->info('Daily report dispatch completed.');
        Log::info('Scheduler: DispatchDailyReports command completed.');
    }
}