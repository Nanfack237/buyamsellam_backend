<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Store;
use App\Mail\DailyReportMail;
use App\Services\DailyReportService;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendStoreDailyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public Store $store;
    public Carbon $reportDate;

    public function __construct(Store $store, Carbon $reportDate)
    {
        $this->store = $store;
        $this->reportDate = $reportDate;
        $this->onQueue('emails');
    }

    public function handle(DailyReportService $dailyReportService): void
    {
        try {
            $reportData = $dailyReportService->getDailyReport($this->store, $this->reportDate);
            Mail::to($reportData['owner_email'])->send(new DailyReportMail($reportData, $this->reportDate));
            Log::info("Daily report for store '{$this->store->name}' (ID: {$this->store->id}) sent successfully to {$reportData['owner_email']}.");
        } catch (\Exception $e) {
            Log::error("Failed to send daily report for store '{$this->store->name}' (ID: {$this->store->id}): " . $e->getMessage(), ['exception' => $e]);
        }
    }
}