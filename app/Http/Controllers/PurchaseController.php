<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Store;
use App\Models\Stock;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\App; // For setting locale
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseController extends Controller
{
    public function list(Request $request){
        
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $purchases = Purchase::where('store_id', $store_id)->get();

        if($purchases){

            $status = 200;
            $response = [
                'success' => 'Purchases',
                'purchases' => $purchases,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to find purchases',
            ];

        }

        return response()->json($response, $status);

    }

    public function stockControllerPurchase(Request $request){

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $purchases = Purchase::where('user_id', $user_id)->get();

        if($purchases){

            $status = 200;
            $response = [
                'success' => 'Purchases',
                'purchases' => $purchases,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to find purchases',
            ];

        }

        return response()->json($response, $status);

    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [

            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|integer',
            'unit_price' => 'required|integer|min:0',
            'selling_price' => 'required|integer|min:0'

        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storePurchaseData = $validator->validated();
        $product_id = $storePurchaseData['product_id'];
        $supplier_id = $storePurchaseData['supplier_id'];
        $quantity = $storePurchaseData['quantity'];
        $date = now()->toDateString();
        $unit_price = $storePurchaseData['unit_price'];
        $selling_price = $storePurchaseData['selling_price'];
        $total_price = $quantity * $unit_price;

        $product = Product::find($product_id);
        $store = Store::find($store_id);
        $supplier = Supplier::find($supplier_id);


        if(!$product || !$supplier || !$store){
            $status = 422;
            $response = [
                'error' => 'Error, this product or supplier does not exist!',
            ];

        } else {

            $purchase = Purchase::create([

                'product_id' => $product_id,
                'store_id' => $store_id,
                'user_id' => $user_id,
                'stock_id' => 1,
                'supplier_id' => $supplier_id,
                'quantity' => $quantity,
                'date' => $date,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'status' => 1,

            ]);

            if($purchase){

                $stock_check = Stock::where('product_id', $product_id)
                                        ->where('store_id', $store_id)
                                        ->where('cost_price', $unit_price)
                                        ->first();

                if(!$stock_check){

                    $stock = Stock::create([

                        'product_id' => $product_id,
                        'store_id' => $store_id,
                        'user_id' => $user_id,
                        'quantity' => $quantity,
                        'cost_price' => $unit_price,
                        'selling_price' => $selling_price,
                        'last_quantity' => 0,
                        'threshold_quantity' => 10,
                        'status' => 1

                    ]);
                    $status = 201;
                    $response = [
                        'success' => 'Purchase transaction saved and new stock created successfully  !',
                        'purchase' => $purchase,
                        'stock' => $stock,
                    ];

                } else {

                    $currentqtty = $stock_check->quantity;
                    $newqtty = $currentqtty + $quantity;
                    $stock_check->quantity = $newqtty;
                    $stock_check->last_quantity = $currentqtty;

                    if($stock_check->save()){

                        $status = 200;
                        $response = [
                            'success' => 'Purchase transaction saved and stock added successfully!',
                            'purchase' => $purchase,
                            'stock' => $stock_check,
                            'quantity_added' => $quantity
                        ];

                    } else {

                        $status = 201;
                        $response = [
                            'error' => 'Purchase transaction saved successfully but stock not added !',
                        ];

                    }
                }

            } else {

                $status = 422;
                $response = [
                    'error' => 'Error, failed to store purchase transaction !',
                ];

            }
        }

        return response()->json($response, $status);

    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $purchase = Purchase::findOrFail($id);

        $editPurchaseData = $validator->validated();

        $purchase->quantity = $editPurchaseData['quantity'];
        $purchase->price = $editPurchaseData['price'];

        if ($purchase->save()) {
            $status = 201;
            $response = [
                'success' => 'Purchase edited successfully!',
                'purchase' => $purchase,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to edit the purchase transaction!',
            ];
        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $purchase = Purchase::where('id', $id)
                             ->where('store_id', $store_id)
                             ->first();
    
        if ($purchase) {
            $status = 200;
            $response = [
                'success' => 'Purchase',
                'purchase' => $purchase,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find the purchase transaction',
            ];
        }
    
        return response()->json($response, $status);
    }

    // public function delete(Request $request, $id)
    // {

    //     $storeData = json_decode($request->store, true);
    //     $store_id = $storeData['id'];

    //     $purchase = Purchase::where('id', $id)
    //                          ->where('store_id', $store_id)
    //                          ->first();

    //     if($purchase->delete()){
    //         $status = 200;
    //         $response = [
    //             'success' => 'Purchase transaction deleted successfully',
    //         ];
    //     } else {
    //         $status = 422;
    //         $response = [
    //             'error' => 'error, failed to delete purchase transaction',
    //         ];
    //     }

    //     return response()->json($response, $status);
    // }

    public function totalPurchasePerDay(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $date = now()->toDateString();
        $totalPurchasePerDay = Purchase::where('store_id', $store_id)->where('date', $date)->count();

        $status = 200;
        $response = [
            'success'=> true,
            'totalPurchasePerDay'=> $totalPurchasePerDay
        ];

        return response()->json($response, $status);
    }

    public function purchaseWeek(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(7);

        $purchases = Purchase::whereBetween('date', [$startDate, $endDate])
        ->selectRaw('DAYOFWEEK(date) as weekday, SUM(unit_price) as total')
        ->where('store_id', $store_id)
        ->groupBy('weekday')
        ->orderBy('weekday')
        ->get();

        $weeklyTotals = array_fill_keys([2,3,4,5,6,7,1], 0);

        foreach ($purchases as $purchase){
            $weekday = (int) $purchase->weekday;
            $weeklyTotals[$weekday] = (float) $purchase->total;

        }
        
        $status = 200;
        $response = [
            'labels'=> ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'data'=> array_values($weeklyTotals),
        ];

        return response()->json($response, $status);
    }


    private function applyFilters($query, array $filters)
    {
        // Filter by store
        if (isset($filters['store'])) {
            $storeData = json_decode($filters['store'], true);
            $store_id = $storeData['id'] ?? null;
            if ($store_id) {
                // Assuming 'purchases' is the primary table for purchases
                $query->where('purchases.store_id', $store_id);
            }
        }
        return $query;
    }


    // public function totalPurchasePerPeriod(Request $request)
    // {
    //     $query = Purchase::query();
    //     $query = $this->applyFilters($query, $request->all());

    //     $totalPurchasePerPeriod = $query->sum('total_amount');

    //     return response()->json(['totalPurchasePerPeriod' => $totalPurchasePerPeriod]);
    // }

    /**
     * Get the count of total purchases for a given period.
     * Endpoint: POST /api/purchases/totalpurchasecountperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
        public function totalPurchaseCountPerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'store' => 'required|json', // Store is typically required for purchase analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . (Carbon::now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        // 2. Start the query with the Purchase model.
        $query = Purchase::query();

        // 3. Apply common filters (store_id).
        $query = $this->applyFilters($query, $request->all());

        // 4. Determine the date range based on user input, with priority given to more specific periods.
        $startDate = null;
        $endDate = null;

        $yearInput = $request->input('year');
        $monthInput = $request->input('month');
        $weekInput = $request->input('week');
        $directStartDate = $request->input('start_date');
        $directEndDate = $request->input('end_date');

        // Determine the ISO year for week-based calculations, defaulting to current year if 'year' is not provided
        $isoYear = $yearInput ?? Carbon::now()->year;

        if ($weekInput) {
            // Highest Priority: If a specific week is selected, calculate its exact start and end dates
            $startDate = (new Carbon())->setISODate($isoYear, $weekInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endDate = $startDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } elseif ($monthInput && $yearInput) {
            // Next Priority: If a specific month and year are selected
            $startDate = Carbon::createFromDate($yearInput, $monthInput, 1)->startOfDay();
            $endDate = Carbon::createFromDate($yearInput, $monthInput, 1)->endOfMonth()->endOfDay();
        } elseif ($yearInput) {
            // Next Priority: If only a year is selected
            $startDate = Carbon::createFromDate($yearInput, 1, 1)->startOfDay();
            $endDate = Carbon::createFromDate($yearInput, 12, 31)->endOfDay();
        } elseif ($directStartDate && $directEndDate) {
            // Next Priority: If direct start and end dates are provided
            $startDate = Carbon::parse($directStartDate)->startOfDay();
            $endDate = Carbon::parse($directEndDate)->endOfDay();
        }
        // If none of the above date filters are provided, no date range will be applied,
        // and the count will cover all purchases for the selected store.

        // 5. Apply the determined date range to the query if one was set.
        if ($startDate && $endDate) {
            $query->whereBetween('purchases.date', [$startDate, $endDate]);
        }

        // 6. Use count() to get the number of purchase records (transactions) for the filtered period.
        $totalPurchaseCount = $query->count();

        // 7. Return the calculated total purchase count as a JSON response.
        return response()->json(['totalPurchaseCount' => $totalPurchaseCount], Response::HTTP_OK);
    }

    /**
     * Get total purchase amount for a given period.
     * Endpoint: POST /api/purchases/totalpurchaseperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalPurchasePerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $request->validate([
            'store' => 'required|json', // Store is typically required for purchase analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        // 2. Start the query with the Purchase model.
        $query = Purchase::query();

        // 3. Apply common filters (store, date range: year, month, week, start_date, end_date).
        // The applyFilters method will set the correct date range on the 'purchases.date' column
        // and handle the 'purchases.store_id' filter.
        $query = $this->applyFilters($query, $request->all());

        // 4. Sum the total purchase amount for the entire filtered period.
        // This assumes your 'purchases' table has a 'total_amount' column that stores the total
        // value of each purchase transaction.
        // If your purchase data is stored differently (e.g., individual items with quantity/cost_price),
        // you might need to join to a 'purchase_items' table and sum accordingly.
        $totalPurchasePerPeriod = $query->sum('total_amount');

        // 5. Return the calculated total purchase amount as a JSON response.
        return response()->json(['totalPurchasePerPeriod' => (float) $totalPurchasePerPeriod]); // Cast to float for clear numeric output
    }


    /**
     * Get purchase summary data for charting (e.g., purchases per day/month).
     * Endpoint: POST /api/purchases/purchasesummaryperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchaseSummaryPerPeriod(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'store' => 'required|json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . (Carbon::now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
            'locale' => 'nullable|string|in:en,fr',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        $labels = [];
        $data = [];
        $store_id = json_decode($request->input('store'), true)['id'] ?? null;

        // 2. Set Locale
        $locale = $request->input('locale', 'en');
        App::setLocale($locale);
        Carbon::setLocale($locale);

        // Start with a fresh query and apply common filters (e.g., store_id, broad date range)
        // The applyFilters method should ensure the initial query scope.
        $purchaseQuery = Purchase::query();

        // Pass the store_id to applyFilters for initial filtering
        $purchaseQuery = $this->applyFilters($purchaseQuery, $request->all());

        $startDateInput = $request->input('start_date'); // Note: These are now less critical for week-specific logic
        $endDateInput = $request->input('end_date');
        $yearInput = $request->input('year');
        $monthInput = $request->input('month');
        $weekInput = $request->input('week');

        // Determine the ISO year for the week. If not provided, assume current year.
        $isoYear = $yearInput ?? Carbon::now()->year;

        // Condition 1: If a SPECIFIC WEEK is selected (Highest Priority - Daily Purchases within Week)
        // This implicitly relies on start_date and end_date encompassing that week.
        if ($weekInput) { // Only check for weekInput, derive dates
            $startDate = (new Carbon())->setISODate($isoYear, $weekInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endDate = $startDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Ensure the query is explicitly bound by the exact date range for daily breakdown
            $purchaseQuery->whereBetween('date', [$startDate, $endDate]);

            $purchaseQuery->selectRaw("DATE_FORMAT(date, '%Y-%m-%d') as purchase_date, SUM(total_price) as total_purchases");
            $purchaseQuery->groupBy('purchase_date');
            $purchaseQuery->orderBy('purchase_date');

            $results = $purchaseQuery->get();

            $dailyPurchaseData = [];
            foreach ($results as $row) {
                $dailyPurchaseData[$row->purchase_date] = (float) $row->total_purchases;
            }

            $orderedLabels = [];
            $orderedData = [];

            // Iterate through each day in the specified range to ensure all days are represented
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dateString = $currentDate->format('Y-m-d');
                // Apply ucfirst for French locale
                if ($locale === 'fr') {
                    $dayAbbr = ucfirst($currentDate->translatedFormat('D'));
                    $monthAbbr = ucfirst($currentDate->translatedFormat('M'));
                    $orderedLabels[] = $dayAbbr . ', ' . $monthAbbr . ' ' . $currentDate->format('d');
                } else {
                    $orderedLabels[] = $currentDate->translatedFormat('D, M d');
                }
                $orderedData[] = $dailyPurchaseData[$dateString] ?? 0.0;
                $currentDate->addDay();
            }

            $labels = $orderedLabels;
            $data = $orderedData;
        }
        // Condition 2: Else if a MONTH and YEAR are selected (Weekly Purchases within Month)
        elseif ($monthInput && $yearInput) {
            $startDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->startOfDay();
            $endDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->endOfMonth()->endOfDay();

            // Ensure the purchase query is limited to the selected month for aggregation
            $purchaseQuery->whereBetween('date', [$startDateOfMonth, $endDateOfMonth]);

            // Group by ISO year and week number (MySQL %x%v ensures Monday-start week)
            $purchaseQuery->selectRaw("DATE_FORMAT(date, '%x%v') as period_key, SUM(total_price) as total_purchases");
            $purchaseQuery->groupBy('period_key');
            $purchaseQuery->orderBy('period_key');

            $results = $purchaseQuery->get();

            $tempWeeklyData = [];
            foreach ($results as $row) {
                // Ensure period_key is treated as an integer for consistent lookup
                $tempWeeklyData[(int) $row->period_key] = (float) $row->total_purchases;
            }

            $rawWeekData = [];
            $processedPeriodKeys = []; // To avoid duplicate week entries, crucial for weeks spanning months

            // Iterate day by day through the month to capture all weeks that touch it
            $currentDayInLoop = $startDateOfMonth->copy();
            while ($currentDayInLoop->lte($endDateOfMonth)) {
                $weekNum = (int) $currentDayInLoop->format('W'); // Carbon's ISO week number (1-53), Monday-start
                $isoYear = (int) $currentDayInLoop->format('o'); // Carbon's ISO year

                $periodKey = (int) ($isoYear . sprintf('%02d', $weekNum));

                if (!isset($processedPeriodKeys[$periodKey])) {
                    $processedPeriodKeys[$periodKey] = true;

                    // Calculate the true start and end dates of this ISO week
                    $weekStartDate = (new Carbon())->setISODate($isoYear, $weekNum)->startOfWeek(Carbon::MONDAY); // Sets to Monday
                    $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY); // Sets to Sunday

                    // Determine the actual display range for this week segment within the month
                    $displayWeekStart = $weekStartDate->max($startDateOfMonth);
                    $displayWeekEnd = $weekEndDate->min($endDateOfMonth);

                    if ($displayWeekStart->lte($displayWeekEnd)) {
                        $weekLabelPrefix = ($locale === 'fr') ? 'Semaine ' : 'Week ';

                        // Apply ucfirst for French locale for date parts
                        $startFormatted = '';
                        $endFormatted = '';
                        if ($locale === 'fr') {
                            $startDayAbbr = ucfirst($displayWeekStart->translatedFormat('D'));
                            $startMonthAbbr = ucfirst($displayWeekStart->translatedFormat('M'));
                            $startFormatted = $startDayAbbr . ', ' . $startMonthAbbr . ' ' . $displayWeekStart->format('d');

                            $endDayAbbr = ucfirst($displayWeekEnd->translatedFormat('D'));
                            $endMonthAbbr = ucfirst($displayWeekEnd->translatedFormat('M'));
                            $endFormatted = $endDayAbbr . ', ' . $endMonthAbbr . ' ' . $displayWeekEnd->format('d');
                        } else {
                            $startFormatted = $displayWeekStart->translatedFormat('D, M d');
                            $endFormatted = $displayWeekEnd->translatedFormat('D, M d');
                        }

                        $label = $weekLabelPrefix . $weekNum . " (" . $startFormatted . " - " . $endFormatted . ")";
                        $value = $tempWeeklyData[$periodKey] ?? 0.0;

                        $rawWeekData[] = [
                            'week_num' => $weekNum,
                            'label' => $label,
                            'data' => $value,
                            'iso_period_key' => $periodKey
                        ];
                    }
                }
                $currentDayInLoop->addDay(); // Move to the next day
            }

            // Sort the collected weekly data chronologically by ISO period key
            usort($rawWeekData, function($a, $b) {
                return $a['iso_period_key'] <=> $b['iso_period_key'];
            });

            // Populate the final labels and data arrays
            foreach ($rawWeekData as $item) {
                $labels[] = $item['label'];
                $data[] = $item['data'];
            }
        }
        // Condition 3: Else if ONLY a YEAR is selected (Monthly Purchases)
        elseif ($yearInput) {
            $startDateOfYear = Carbon::createFromDate($yearInput, 1, 1)->startOfDay();
            $endDateOfYear = Carbon::createFromDate($yearInput, 12, 31)->endOfDay();
            $purchaseQuery->whereBetween('date', [$startDateOfYear, $endDateOfYear]);

            $purchaseQuery->selectRaw('MONTH(date) as period_key, SUM(total_price) as total_purchases');
            $purchaseQuery->groupBy('period_key');
            $purchaseQuery->orderBy('period_key');

            $results = $purchaseQuery->get();

            // Populate labels with translated month abbreviations
            for ($m = 1; $m <= 12; $m++) {
                $monthLabel = Carbon::create(null, $m, 1)->translatedFormat('M');
                // Apply ucfirst for French locale
                if ($locale === 'fr') {
                    $labels[] = ucfirst($monthLabel);
                } else {
                    $labels[] = $monthLabel;
                }
            }

            $tempData = array_fill(1, 12, 0.0); // Initialize with 0.0 for all months
            foreach ($results as $row) {
                $tempData[(int) $row->period_key] = (float) $row->total_purchases;
            }
            $data = array_values($tempData); // Get values in month order
        }
        // Default Condition (Fallback): Show ALL years (Yearly Purchases)
        else {
            // Determine the historical range dynamically from the first purchase record for the store
            $firstPurchaseRecord = Purchase::when($store_id, function ($query) use ($store_id) {
                return $query->where('store_id', $store_id);
            })->orderBy('date', 'asc')->first();

            $startHistoricalYear = $firstPurchaseRecord ? Carbon::parse($firstPurchaseRecord->date)->year : Carbon::now()->subYears(5)->year; // Default to 5 years back if no records
            $currentYear = Carbon::now()->year;

            // Ensure the start historical year is not in the future
            if ($startHistoricalYear > $currentYear) {
                $startHistoricalYear = $currentYear;
            }

            // Ensure the purchase query is limited to the range of years for aggregation
            $purchaseQuery->whereBetween('date', [
                Carbon::createFromDate($startHistoricalYear, 1, 1)->startOfDay(),
                Carbon::createFromDate($currentYear, 12, 31)->endOfDay()
            ]);

            $purchaseQuery->selectRaw('YEAR(date) as period_key, SUM(total_price) as total_purchases');
            $purchaseQuery->groupBy('period_key');
            $purchaseQuery->orderBy('period_key');

            $results = $purchaseQuery->get();

            // Initialize data array for all years in the range
            $tempData = array_fill_keys(range($startHistoricalYear, $currentYear), 0.0);
            $labels = array_map('strval', range($startHistoricalYear, $currentYear)); // Labels are just the years

            foreach ($results as $row) {
                if (isset($tempData[(int) $row->period_key])) {
                    $tempData[(int) $row->period_key] = (float) $row->total_purchases;
                }
            }
            $data = array_values($tempData); // Get values in chronological year order
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ], Response::HTTP_OK);
    }

    // No filter

    public function totalPurchaseCountAll(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $date = now()->toDateString();
        $totalPurchasePerDay = Purchase::where('store_id', $store_id)->count();

        $status = 200;
        $response = [
            'success'=> true,
            'totalPurchaseCountAll'=> $totalPurchasePerDay
        ];

        return response()->json($response, $status);
    }
    
}

