<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        h2 { color: #0056b3; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .profit { color: #28a745; font-weight: bold; }
        .loss { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2>{{ __('reports.subject', ['store_name' => $report['store_name'], 'date' => $report['report_date']->format('Y-m-d')]) }}</h2>
        <p>{{ __('reports.dear_manager') }}</p>
        <p>{{ __('reports.summary_intro', ['store_name' => '<strong>' . $report['store_name'] . '</strong>']) }}</p>

        <p><strong>{{ __('reports.total_stock') }}</strong> {{ number_format($report['total_stock_level']) }} units</p>
        <p><strong>{{ __('reports.total_sales_revenue') }}</strong> ${{ number_format($report['total_sales_amount'], 2) }} ({{ __('reports.transactions', ['count' => $report['sale_transactions_count']]) }})</p>
        <p><strong>{{ __('reports.total_purchases_cost') }}</strong> ${{ number_format($report['total_purchases_amount'], 2) }} ({{ __('reports.transactions', ['count' => $report['purchase_transactions_count']]) }})</p>
        <p>
            <strong>{{ __('reports.daily_profit_loss') }}</strong>
            @if ($report['total_profit_amount'] >= 0)
                <strong class="profit">${{ number_format($report['total_profit_amount'], 2) }} ({{ __('reports.profit') }})</strong>
            @else
                <strong class="loss">${{ number_format(abs($report['total_profit_amount']), 2) }} ({{ __('reports.loss') }})</strong>
            @endif
        </p>

        <p style="margin-top: 30px;">{{ __('reports.thank_you') }}</p>
        <p>{{ __('reports.automated_system') }}</p>
    </div>
</body>
</html>