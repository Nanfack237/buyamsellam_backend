<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stock;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class StockController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $stocks = Stock::where('store_id', $store_id)->get();

        if($stocks){

            $status = 200;
            $response = [
                'success' => 'Stock',
                'stocks' => $stocks,
            ];
            
        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list stocks',
            ];

        }

        return response()->json($response, $status);
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity'=>'required|integer',
            'cost_price'=>'required|integer',
            'selling_price'=>'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $saveStoreData = $validator->validated();
        $stock = Stock::create([
            'product_id' => $saveStoreData['product_id'],
            'store_id'=> $store_id,
            'quantity' => $saveStoreData['quantity'],
            'cost_price' => $saveStoreData['cost_price'],
            'selling_price' => $saveStoreData['selling_price'],
            'last_quantity'=> 0,
        ]);
        if ($stock) {

            $status = 201;
            $response = [
                'success' => 'Stock stored successfully !',
                'stock' => $stock,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to store the stock!',
            ];
        }

        return response()->json($response, $status);

    }

    public function show(Request $request, $id)
    {
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $stock = Stock::where('store_id', $store_id)->where('user_id', $user_id)->find($id);

        if($stock){
            $status = 200;
            $response = [
                'success' => 'stock',
                'stock' => $stock,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to find the stock',
            ];
        }


        return response()->json($response, $status);

    }

    public function totalStock(Request $request)
    {

        $storeData = json_decode($request->store, true);

        $store_id = $storeData['id'];



        $totalStock = Stock::where('store_id', $store_id)->sum('quantity');



        $status = 200;

        $response = [

            'success' => true,

            'totalStock' => $totalStock

        ];



        return response()->json($response, $status);

    }



    public function shortageCount(Request $request){

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $stock_threshold = Stock::where('store_id', $store_id)->first();

        $threshold_quantity = $stock_threshold->threshold_quantity;

        $shortage = Stock::where('store_id', $store_id)->where('quantity', '<=', $threshold_quantity)->get();

        $status = 200;
        $response = [
            'success' => true,
            'shortage' => $shortage,
        ];

        return response()->json($response, $status);

    }


    protected function applyFilters(Builder $query, array $filters) // Type hint Builder for clarity
    {
        if (isset($filters['store'])) {
            // Decode the JSON string as an associative array (important for {"id": X} format)
            $decodedStore = json_decode($filters['store'] ?? '{}', true);

            $storeIdsToFilter = [];

            if (is_array($decodedStore)) {
                if (isset($decodedStore['id']) && (is_numeric($decodedStore['id']))) {
                    // Scenario: JSON was {"id": 123}. Extract the single ID.
                    $storeIdsToFilter[] = $decodedStore['id'];
                } else {
                    // Scenario: JSON was [1, 2, 3] or an empty array.
                    // Filter out any non-numeric values to ensure clean IDs for whereIn.
                    $storeIdsToFilter = array_filter($decodedStore, 'is_numeric');
                }
            }

            if (!empty($storeIdsToFilter)) {
                // Apply the filter to the *existing* $query object using whereIn for flexibility
                $query->whereIn('store_id', $storeIdsToFilter);
            }
        }
        return $query;
    }

     public function totalStockPerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $request->validate([
            'store' => 'required|json',
            'end_date' => 'nullable|date', // end_date is the primary explicit date filter
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        // 2. Start the query with the Stock model.
        $query = Stock::query();

        // 3. Apply common filters, specifically the store filter, using the helper method.
        $query = $this->applyFilters($query, $request->all());

        // 4. Initialize date boundaries
        // Default start: Beginning of time (Unix Epoch 1970-01-01 00:00:00 UTC)
        // This is used as the earliest possible record date if no start point is specified.
        $start_period = Carbon::createFromTimestamp(0);
        // Default end: End of today (current date at 23:59:59)
        $end_period = Carbon::today()->endOfDay();

        // 5. Determine the date range based on priority:
        //    a) Explicit 'end_date' (highest priority)
        //    b) 'year' / 'month' / 'week' combination
        //    c) Default to 'beginning of time' till 'end of today' (if no date filters given)

        if ($request->has('end_date')) {
            // Priority 1: Explicit 'end_date' provided.
            // If an explicit end_date is given, the calculation will always be
            // from the very first stock entry until this specified end_date.
            $end_period = Carbon::parse($request->input('end_date'))->endOfDay();
            $start_period = Carbon::createFromTimestamp(0); // Set start to beginning of time
        } elseif ($request->has('year')) {
            // Priority 2: 'year', 'month', 'week' combination (only if no explicit 'end_date')
            $year = $request->input('year');
            $month = $request->input('month');
            $week = $request->input('week');

            try {
                if ($month && $week) {
                    // Both month and week provided. Attempt to find the specific week.
                    $tempDateForWeek = Carbon::createFromDate($year)->setISODate($year, $week);

                    // Check if the calculated week's month matches the provided month.
                    if ($tempDateForWeek->month === $month) {
                        // Week is consistent with month: use the specific week's range.
                        $start_period = $tempDateForWeek->startOfWeek();
                        $end_period = $tempDateForWeek->endOfWeek();
                    } else {
                        // Week does NOT belong to the specified month (e.g., Week 1 in June).
                        // Fallback: Sum from the beginning of the year up to the end of the specified month.
                        $start_period = Carbon::create($year, 1, 1)->startOfYear();
                        $end_period = Carbon::create($year, $month)->endOfMonth();
                    }
                } elseif ($month) {
                    // Only 'year' and 'month' provided (or 'week' was inconsistent).
                    // Sum from the beginning of the year up to the end of the specified month.
                    $start_period = Carbon::create($year, 1, 1)->startOfYear();
                    $end_period = Carbon::create($year, $month)->endOfMonth();
                } else {
                    // Only 'year' provided.
                    // Sum from the beginning of the year to the end of the year.
                    $start_period = Carbon::create($year, 1, 1)->startOfYear();
                    $end_period = Carbon::create($year, 12, 31)->endOfYear();
                }
            } catch (\Exception $e) {
                // Handle potential parsing errors for invalid date components (e.g., invalid month number).
                // If an error occurs, and a valid 'year' was originally intended, fall back to the entire year.
                // Otherwise, the default start_period and end_period (beginning of time to today) will be used.
                if ($request->has('year') && is_numeric($request->input('year'))) {
                    $start_period = Carbon::create( $request->input('year'), 1, 1)->startOfYear();
                    $end_period = Carbon::create( $request->input('year'), 12, 31)->endOfYear();
                }
            }
        }
        // If none of the above conditions are met (no end_date, year, month, or week provided,
        // or all provided date filters were invalid and not recovered),
        // the $start_period and $end_period will remain at their initial defaults:
        // beginning of time (Unix Epoch) to the end of today.


        // 6. Apply the determined date range filter to the query.
        // It's crucial that 'created_at' (or your relevant stock date column)
        // is being used here for filtering the stock records.
        $query->whereBetween('created_at', [$start_period, $end_period]);

        // 7. Sum the 'quantity' column.
        // This directly sums the 'quantity' field from the 'stocks' table.
        $totalStock = $query->sum('quantity');

        // FOR DEBUGGING: Uncomment the lines below to see the executed SQL query
        // DB::enableQueryLog();
        // $totalStock = (float) $query->sum('quantity'); // Re-run sum after enabling log
        // $queries = DB::getQueryLog();
        // \Log::info('Total Stock Query Debug:', ['sql' => $queries]); // Check storage/logs/laravel.log
        // dd($queries); // For direct browser output

        // 8. Return the calculated total stock as a JSON response.
        return response()->json([
            'success' => true,
            'totalStock' => $totalStock
        ], 200);
    }

    // public function totalStockPerPeriodNofilter(){

    // }


    // settings

     public function updateGlobalStockThreshold(Request $request)
    {
        // 1. Validate incoming request parameters
        $validator = Validator::make($request->all(), [
           
            'threshold_quantity' => 'required|integer|min:0', // min:0 allows setting threshold to 0
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        // Extract store ID and new threshold quantity
        $storeData = json_decode($request->store, true); // Use input() for safety
        $store_id = $storeData['id'] ?? null; // Safely get store ID

        $newThreshold = $request->input('threshold_quantity');

        // Basic validation for extracted IDs
        if (!$store_id) {
            return response()->json(['message' => 'Invalid store ID provided.'], Response::HTTP_BAD_REQUEST); // 400
        }

        try {
            // Update the 'threshold_quantity' column for all stocks associated with the given store_id
            $updatedCount = Stock::where('store_id', $store_id)->update([
                'threshold_quantity' => $newThreshold
            ]);

            // You might want to log the update for auditing
            \Log::info("Global stock threshold updated for store_id: {$store_id} to {$newThreshold}. {$updatedCount} records affected.");

            return response()->json([
                'success' => true,
                'message' => "Global stock threshold updated for {$updatedCount} products successfully!"
            ], Response::HTTP_OK); // Use 200 for success

        } catch (\Exception $e) {
            \Log::error('Failed to update global stock threshold: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to update global stock threshold. An error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500
        }
    }
}


