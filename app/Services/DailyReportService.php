<?php
namespace App\Services;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DailyReportService
{
    public function getStoreName(Store $store): string { return $store->name; }

    public function getTotalStockQuantity(Store $store): int
    {
        return $store->stocks()->sum('quantity') ?? 0;
    }

    public function getTotalPurchasesForDay(Store $store, Carbon $date): array
    {
        $purchases = $store->purchases()->whereDate('date', $date)->get();
        return [
            'amount' => $purchases->sum('total_price'),
            'transactions' => $purchases->count(),
        ];
    }

    public function getTotalSalesForDay(Store $store, Carbon $date): array
    {
        $sales = $store->sales()->whereDate('date', $date)->get();
        return [
            'amount' => $sales->sum('total_price'),
            'transactions' => $sales->count(),
        ];
    }

    public function getTotalProfitForDay(Store $store, Carbon $date): float
    {
        $sales = $store->sales()->with('stock')->whereDate('date', $date)->get();
        $totalSalesRevenue = $sales->sum('total_price');
        $totalCostOfSoldGoods = 0;
        foreach ($sales as $sale) {
            if ($sale->stock && $sale->stock->cost_price) {
                $totalCostOfSoldGoods += ($sale->stock->cost_price * $sale->quantity);
            } else {
                Log::warning("Sale ID {$sale->id} for store {$store->name} has no associated stock record for COGS calculation.");
            }
        }
        return $totalSalesRevenue - $totalCostOfSoldGoods;
    }

    public function getDailyReport(Store $store, Carbon $date): array
    {
        $owner = $store->owner;
        $ownerEmail = $owner->email ?? null;
        $ownerLocale = $owner->locale ?? config('app.locale');

        if (!$ownerEmail) {
            Log::error("Owner email not found for store '{$store->name}' (ID: {$store->id}). Cannot generate report.");
            throw new \Exception("Owner email not found for store '{$store->name}'.");
        }

        $storeName = $this->getStoreName($store);
        $totalStock = $this->getTotalStockQuantity($store);
        $purchases = $this->getTotalPurchasesForDay($store, $date);
        $sales = $this->getTotalSalesForDay($store, $date);
        $profit = $this->getTotalProfitForDay($store, $date);

        return [
            'store_name' => $storeName,
            'total_stock_level' => $totalStock,
            'total_purchases_amount' => $purchases['amount'],
            'purchase_transactions_count' => $purchases['transactions'],
            'total_sales_amount' => $sales['amount'],
            'sale_transactions_count' => $sales['transactions'],
            'total_profit_amount' => $profit,
            'report_date' => $date,
            'owner_email' => $ownerEmail,
            'owner_locale' => $ownerLocale,
        ];
    }
}