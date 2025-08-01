<!DOCTYPE html>
<html>
<head>
    <title>{{ __('receipt.title', ['receipt_id' => $details['receipt_id']]) }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; color: #333; font-size: 14px;}
        .container { width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 20px; }
        .store-name { font-size: 28px; margin-bottom: 5px; color: rgb(13, 71, 161, 1); font-weight: bold; }
        .store-info { font-size: 13px; color: #777; margin-top: 0; line-height: 1.2; }
        .logo { max-width: 100px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;}
        .title-section { text-align: center; margin: 20px 0; border-top: 1px dashed #ddd; padding-top: 15px;}
        .receipt-title { font-size: 22px; margin-bottom: 5px; color: #555; }
        .receipt-id { font-size: 14px; color: #888; }
        .info-block { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #ddd; }
        .info-block p { margin: 8px 0; font-size: 14px; }
        .info-block strong { color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { border: 1px solid #eee; padding: 10px; text-align: left; font-size: 13px;}
        table th { background-color: #f5f5f5; font-weight: bold; color: #444;}
        .total-section { text-align: right; font-size: 20px; font-weight: bold; margin-top: 20px; padding-top: 15px; border-top: 2px solid #555; }
        .footer { text-align: center; margin-top: 30px; font-size: 13px; color: #666; line-height: 1.5; }
        .powered-by { font-style: italic; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if ($details['store_logo_url'])
                <img src="{{ $details['store_logo_url'] }}" alt="Store Logo" class="logo">
            @endif
            <h2 class="store-name">{{ $details['store_name'] }}</h2>
            <p class="store-info">{{ $details['store_location'] }} | {{ $details['store_contact'] }}</p>
        </div>

        <div class="title-section">
            <h3 class="receipt-title">{{ __('receipt.receipt_title') }}</h3>
            <p class="receipt-id">{{ __('receipt.receipt_id') }} {{ $details['receipt_id'] }}</p>
        </div>

        <div class="info-block">
            <p><strong>{{ __('receipt.customer') }}</strong> {{ $details['customer_name'] }}</p>
            <p><strong>{{ __('receipt.cashier') }}</strong> {{ $details['cashier_name'] }}</p>
            <p><strong>{{ __('receipt.date') }}</strong> {{ $details['sale_date'] }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('receipt.table_header_sn') }}</th>
                    <th>{{ __('receipt.table_header_product') }}</th>
                    <th>{{ __('receipt.table_header_quantity') }}</th>
                    <th>{{ __('receipt.table_header_unit_price')  }}{{ __('receipt.currency') }}</th>
                    <th>{{ __('receipt.table_header_total') }}{{ __('receipt.currency') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details['items'] as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ number_format($item['unit_price'], 0, '.', ',') }}</td>
                        <td>{{ number_format($item['total_price'], 0, '.', ',') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-section">
            <p>{{ __('receipt.total') }} <strong> {{ number_format($details['total_amount'], 0, '.', ',') }}{{ __('receipt.currency') }}</strong></p>
        </div>

        <div class="footer">
            <p>{{ __('receipt.thank_you_message') }}</p>
            <p class="powered-by">Powered by <span style="color: rgb(13, 71, 161, 1);">Buyam</span><span style="color:rgba(3, 250, 127, 1)">Sellam</span></p>
        </div>
    </div>
</body>
</html>