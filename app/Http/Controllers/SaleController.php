<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Customer;
use App\Models\Store;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\SaleReceiptMail; // Make sure this is the correct Mailable class name
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class SaleController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $sales = Sale::where('store_id', $store_id)->get();

        if($sales){

            $status = 200;
            $response = [
                'success' => 'Sales',
                'sales' => $sales,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'Error, failed to find sales',
            ];

        }

        return response()->json($response, $status);

    }

    public function cashierList(Request $request)
    {

        $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body
      
       if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }
        $user_id = $userData['id'];

        $sales = Sale::where('user_id', $user_id)->get();

        if ($sales->isNotEmpty()) { // Use isNotEmpty() for collections
            return response()->json([
                'success' => 'Sales retrieved successfully.',
                'sales' => $sales,
            ], 200);
        } else {
            // 404 Not Found if no sales are found for the user
            return response()->json([
                'error' => 'No sales found for this user.',
            ], 404);
        }
    }

    /**
     * Store a new sale transaction, handling stock deduction across multiple batches if necessary.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

         $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }

        $user_id = $userData['id'];
        $userRole = $userData['role']; // Get role from decoded user data

        // Check if the user exists in the database
        $userCheck = User::find($user_id);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'stock_id' => 'required|exists:stocks,id', // Still required, represents the initially selected stock
            'quantity' => 'required|integer|min:1', // Quantity must be at least 1
            'selling_price' => 'required|numeric|min:0', // Numeric to allow decimals if needed
            'customer_name' => 'nullable|string|max:255', // Nullable if it can be empty
            'customer_contact' => 'nullable|integer', // Nullable if it can be empty, frontend sends 0 if empty
            'payment_method' => 'required|string|in:cash,mobile', // Enforce specific payment methods
            'receipt_code' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400); // 400 Bad Request for validation errors
        }

        $storeSaleData = $validator->validated();


        // Get store ID. Assuming it's passed or derived from the user's assignment.
        // For security, it's better to fetch the store_id based on the authenticated user's assigned store
        // instead of trusting raw request data.
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'] ?? null;

        if (!$store_id) {
            return response()->json(['error' => 'Store ID is missing or invalid.'], 400);
        }

        $product_id = $storeSaleData['product_id'];
        $initial_stock_id = $storeSaleData['stock_id']; // The stock_id sent from frontend
        $quantity_requested = $storeSaleData['quantity'];
        $selling_price = $storeSaleData['selling_price'];
        $customer_name = $storeSaleData['customer_name'] ?? 'Anonymous';
        $customer_contact = $storeSaleData['customer_contact'] ?? 0;
        $payment_method = $storeSaleData['payment_method'];
        $receipt_code = $storeSaleData['receipt_code'];
        $date = Carbon::now()->toDateString();

        // Fetch the product to get its name for error messages
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        // --- Stock Deduction Logic ---
        $quantity_to_sell = $quantity_requested;
        $actual_stock_id_used = null;
        $stock_item_used = null;

        // 1. Try to use the initially selected stock_id
        $primary_stock_item = Stock::where('product_id', $product_id)
                                  ->where('store_id', $store_id)
                                  ->where('id', $initial_stock_id)
                                  ->first();

        if ($primary_stock_item && $primary_stock_item->quantity >= $quantity_to_sell) {
            // If primary stock has enough, use it
            $stock_item_used = $primary_stock_item;
            $actual_stock_id_used = $primary_stock_item->id;
        } else {
            // 2. If primary stock is insufficient or not found, look for other stock batches for the same product
            $alternative_stock_items = Stock::where('product_id', $product_id)
                                            ->where('store_id', $store_id)
                                            ->where('quantity', '>', 0) // Only consider batches with positive stock
                                            ->orderByDesc('quantity') // Prioritize larger batches
                                            ->get();

            // Check if any alternative stock can fulfill the order
            foreach ($alternative_stock_items as $alt_stock) {
                if ($alt_stock->quantity >= $quantity_to_sell) {
                    $stock_item_used = $alt_stock;
                    $actual_stock_id_used = $alt_stock->id;
                    break; // Found a suitable batch, exit loop
                }
            }
        }

        // Check if a suitable stock item was found
        if (!$stock_item_used) {
            // If no stock record (either primary or alternative) can fulfill the quantity
            return response()->json([
                'error' => 'Insufficient stock for product "' . $product->name . '". Requested: ' . $quantity_requested . '. Available in total across all batches: ' . Stock::where('product_id', $product_id)->where('store_id', $store_id)->sum('quantity') . '.',
                'quantity_available' => Stock::where('product_id', $product_id)->where('store_id', $store_id)->sum('quantity')
            ], 422); // Unprocessable Entity
        }

        try {
            $total_price = $quantity_requested * $selling_price;

            // Create the Sale record using the 'actual_stock_id_used'
            $sale = Sale::create([
                'product_id' => $product_id,
                'stock_id' => $actual_stock_id_used, // Use the stock ID that actually fulfilled the order
                'store_id' => $store_id,
                'user_id' => $user_id,
                'quantity' => $quantity_requested,
                'date' => $date,
                'selling_price' => $selling_price,
                'total_price' => $total_price,
                'customer_id' => 0, // Will be updated if customer exists/is created
                'customer_name' => $customer_name,
                'customer_contact' => $customer_contact,
                'payment_method' => $payment_method,
                'receipt_code' => $receipt_code,
                'status' => 1, // Assuming 1 means active/completed sale
            ]);

            // Update the quantity of the stock item that was used
            $current_stock_quantity = $stock_item_used->quantity;
            $stock_item_used->last_quantity = $current_stock_quantity; // Store previous quantity
            $stock_item_used->quantity = $current_stock_quantity - $quantity_requested;
            $stock_item_used->save();

            // Handle customer creation/update
            $customer = null;
            if ($customer_name !== 'Anonymous') { // Only try to find/create if not anonymous
                $customer = Customer::where('name', $customer_name)
                                    ->where('store_id', $store_id)
                                    ->first();

                if (!$customer) {
                    $customer = Customer::create([
                        'name' => $customer_name,
                        'contact' => $customer_contact,
                        'store_id' => $store_id,
                        'user_id' => $user_id,
                        'status' => 1
                    ]);
                }
            }

            // Associate customer with sale if customer was found or created
            if ($customer) {
                $sale->customer_id = $customer->id;
                $sale->save(); // Save the sale again to update customer_id
            }

            return response()->json([
                'success' => 'Sale transaction stored and stock deducted successfully!',
                'sale' => $sale,
                'stock_updated' => $stock_item_used, // Return the actual stock item that was updated
                'quantity_remaining' => $stock_item_used->quantity
            ], 201); // 201 Created

        } catch (\Exception $e) {
            Log::error('Error processing sale: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);
            return response()->json(['error' => 'An internal server error occurred while processing the sale.'], 500); // 500 Internal Server Error
        }
    }



    public function edit(Request $request, $id)
    {

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $validator = Validator::make($request->all(), [

            'product_id' => 'required|exists:products,id',
            'stock_id' => 'required|exists:stocks,id',
            'quantity' => 'required|integer',
            'date' => 'required|date',
            'unit_price' => 'required|integer|min:0',
            'total_price' => 'required|integer|min:0',
            'customer_name' => 'required|string',
            'customer_contact' => 'required|integer',

        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $sale = Sale::findOrFail($id);

        $editSaleData = $validator->validated();
        $product_id = $editSaleData['product_id'];
        $store_id = $store_id;
        $stock_id = $editSaleData['stock_id'];
        $quantity = $editSaleData['quantity'];
        $date = $editSaleData['date'];
        $unit_price = $editSaleData['unit_price'];
        $total_price = $quantity * $unit_price;
        $customer_name = $editSaleData['customer_name'];
        $customer_contact = $editSaleData['customer_contact'];

        if ($sale->save()) {

            $status = 201;
            $response = [
                'success' => 'Sale edited successfully!',
                'sale' => $sale,
            ];

        } else {

            // $status = 422;
            $response = [
                'error' => 'Error, failed to edit the sale!',
            ];

        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {

        
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $sale = Sale::where('id', $id)
                             ->where('store_id', $store_id)
                             ->first();

        if($sale){

            $status = 200;
            $response = [
                'success' => 'Sale',
                'sale' => $sale,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to find the sale transaction',
            ];

        }

        return response()->json($response, $status);

    }

    
    public function mostSoldProduct(Request $request)
    {
        // $startDate = '2024-09-01';
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $mostSoldProduct = Sale::join('products', 'sales.product_id', '=', 'products.id')
        ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
        ->select('products.id', 'products.name', DB::raw('SUM(sales.quantity) as total_sold'))
        ->where('sales.store_id', $store_id)
        ->groupBy('products.id', 'products.name')
        ->orderBy('total_sold', 'desc')
        ->limit(5)
        ->get();

        if($mostSoldProduct){
            $status = 200;
            $response = [
                'success' => 'The Most sold product',
                'mostSold' => $mostSoldProduct
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, Fetching the most sold product',
            ];
        }

        return response()->json($response, $status);
    }

    public function mostSoldProductOfTheWeek(Request $request)
    {
        // Get store ID from the request
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        // Define the start and end dates for the current week
        // Assuming week starts on Monday and ends on Sunday
        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Query to get top products for the current week
        $mostSoldProduct = Sale::join('products', 'sales.product_id', '=', 'products.id')
            // The leftJoin('stocks', ...) seems unnecessary here if not directly used for filtering or selection.
            // If it's not strictly needed for the join condition or selecting 'stock' columns, consider removing it.
            ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sales.quantity) as total_sold') // Sum of quantities sold for each product
            )
            ->where('sales.store_id', $store_id)
            ->whereBetween('sales.date', [$startDate, $endDate]) // Filter sales for the current week
            ->groupBy('products.id', 'products.name') // Group by product to sum their quantities
            ->orderBy('total_sold', 'desc') // Order by total quantity sold in descending order
            ->limit(5) // Limit to the top 4 products
            ->get();

        // The query will return an empty collection if no products are found,
        // so a simple check for empty() is sufficient.
        if ($mostSoldProduct->isNotEmpty()) {
            $status = 200;
            $response = [
                'success' => true, // Changed message to boolean for consistency
                'message' => 'Successfully fetched top products for the current week.', // Descriptive message
                'mostSold' => $mostSoldProduct
            ];
        } else {
            $status = 200; // Return 200 with empty array if no data, not 422 for 'error'
            $response = [
                'success' => true, // Still a success, just no data
                'mostSold' => [], // Return an empty array
                'message' => 'No top products found for the current week.'
            ];
        }

        return response()->json($response, $status);
    }

    public function topCustomer(Request $request)
    {
        // $startDate = '2024-09-01';
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $topCustomer = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')
        ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
        ->select('customers.id', 'customers.name', DB::raw('SUM(sales.quantity) as total_bought'))
        ->where('sales.store_id', $store_id)
        ->groupBy('customers.id', 'customers.name')
        ->orderBy('total_bought', 'desc')
        ->limit(5)
        ->get();

        if($topCustomer){
            $status = 200;
            $response = [
                'success' => true,
                'topcustomer' => $topCustomer
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, Fetching the most top customer',
            ];
        }

        return response()->json($response, $status);
    }

    public function topCustomerOfTheWeek(Request $request)
    {
        // Get store ID from the request
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        // Define the start and end dates for the current week
        // Assuming week starts on Monday and ends on Sunday
        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Query to get top customers for the current week
        $topCustomers = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')
            // The leftJoin('stocks', ...) seems unnecessary for just customer quantity,
            // but keeping it if it serves another purpose in your actual Sale model logic.
            // If not, it can be removed for slight performance gain.
            ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('SUM(sales.quantity) as total_bought') // Sum of quantities for each customer
            )
            ->where('sales.store_id', $store_id)
            ->whereBetween('sales.date', [$startDate, $endDate]) // Filter sales for the current week
            ->groupBy('customers.id', 'customers.name') // Group by customer to sum their quantities
            ->orderBy('total_bought', 'desc') // Order by total quantity bought in descending order
            ->limit(5) // Limit to the top 4 customers
            ->get();

        // The query will return an empty collection if no customers are found,
        // so a simple check for empty() is sufficient.
        if ($topCustomers->isNotEmpty()) {
            $status = 200;
            $response = [
                'success' => true,
                'topcustomers' => $topCustomers // Renamed key for consistency
            ];
        } else {
            $status = 200; // It's often 200 with an empty array if no data, not 422 for 'error'
            $response = [
                'success' => true, // Still a success, just no data
                'topcustomers' => [], // Return an empty array
                'message' => 'No top customers found for the current week.'
            ];
        }

        return response()->json($response, $status);
    }

    public function totalProfitPerDay(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];
      
        $date = now()->toDateString();
        $totalProfitPerDay = Sale::join('products', 'sales.product_id', '=', 'products.id')
            ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id') // Assuming `purchase_items`
            ->select(DB::raw('DATE(sales.date) as sale_date'), DB::raw('SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit'))
            ->where('sales.date', $date)  // Replace for date range if needed
            ->where('sales.store_id', $store_id)
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();

        if($totalProfitPerDay){
            $status = 200;
            $response = [
                'success' => 'Total Profit of today',
                'totalprofitperday' => $totalProfitPerDay
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, Fetching the total profit',
            ];
        }

        return response()->json($response, $status);
    }
    // public function delete(Request $request, $id)
    // {

    //     $storeData = json_decode($request->store, true);
    //     $store_id = $storeData['id'];

    //     $sale = Sale::where('id', $id)
    //                          ->where('store_id', $store_id)
    //                          ->first();

    //     if($sale->delete()){

    //         $status = 200;
    //         $response = [
    //             'success' => 'Sale transaction deleted successfully',
    //         ];

    //     } else {

    //         $status = 422;
    //         $response = [
    //             'error' => 'error, failed to delete sale transaction',
    //         ];

    //     }

    //     return response()->json($response, $status);
    // }

    public function totalSalePerDay(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $date = now()->toDateString();
        $totalSalePerDay = Sale::where('store_id', $store_id)
                                ->where('date', $date)
                                ->distinct('receipt_code')   
                                ->count();

        $status = 200;
        $response = [
            'success'=> true,
            'totalSalePerDay'=> $totalSalePerDay
        ];

        return response()->json($response, $status);
    }

    public function saleWeek(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(7);

        $sales = Sale::whereBetween('date', [$startDate, $endDate])
        ->selectRaw('DAYOFWEEK(date) as weekday, SUM(selling_price * quantity) as total')
        ->where('store_id', $store_id)
        ->groupBy('weekday')
        ->orderBy('weekday')
        ->get();

        $weeklyTotals = array_fill_keys([2,3,4,5,6,7,1], 0);

        foreach ($sales as $sale){
            $weekday = (int) $sale->weekday;
            $weeklyTotals[$weekday] = (float) $sale->total;

        }
        
        $status = 200;
        $response = [
            'labels'=> ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday','Sunday'],
            'data'=> array_values($weeklyTotals),
        ];

        return response()->json($response, $status);
    }

    public function salePerMonth(Request $request)
    {
        // Get store ID from the request
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        // Define the start and end dates for the current year
        $startDate = Carbon::now()->startOfYear();
        $endDate = Carbon::now()->endOfYear();

        // Query to get total sales per month
        $sales = Sale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('MONTH(date) as month, SUM(selling_price * quantity) as total') // Select month number and sum of selling_price
            ->where('store_id', $store_id)
            ->groupBy('month') // Group results by month
            ->orderBy('month') // Order by month number for chronological sequence
            ->get();

        // Initialize an array to hold total sales for all 12 months, defaulting to 0
        // Month numbers are 1-12
        $monthlyTotals = array_fill(1, 12, 0.0);

        // Populate the monthlyTotals array with actual data from the query results
        foreach ($sales as $sale) {
            $month = (int) $sale->month;
            $monthlyTotals[$month] = (float) $sale->total;
        }

        // Define month labels corresponding to month numbers 1-12
        $monthLabels = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        // Prepare the response in the format expected by the frontend chart
        $response = [
            'labels' => $monthLabels,
            'data' => array_values($monthlyTotals), // Get only the values, in order
        ];

        return response()->json($response, 200);
    }

    public function profitWeek(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(7);

        $totalProfitPerDay = Sale::join('products', 'sales.product_id', '=', 'products.id')
                    ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
                    ->select(
                        DB::raw('DAYOFWEEK(sales.date) as weekday'),
                        DB::raw('SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as profit')
                    )
                    ->whereBetween('sales.date', [$startDate, $endDate])
                    ->where('sales.store_id', $store_id)
                    ->groupBy('weekday')
                    ->orderBy('weekday')
                    ->get();

            $profits = array_fill_keys([2,3,4,5,6,7,1], 0);

            foreach ($totalProfitPerDay as $row){
                $weekday = (int) $row->weekday;
                $profits[$weekday] = (float) $row->profit;

            }
            $status = 200;
            $response = [
                'labels'=> ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'data'=> array_values($profits),
            ];
        
        return response()->json($response, $status);

    }

    // public function totalProfitPerMonth(Request $request)
    // {
    //     // Get store ID from the request (ensure 'store' is sent from frontend)
    //     // If 'store' is just a stringified JSON, you might need to parse it.
    //     // Or, more robustly, pass store_id directly or get it from authenticated user.
    //     $storeData = json_decode($request->input('store', '{}'), true); // Use input() for GET params
    //     $store_id = $storeData['id'] ?? null; // Null coalesce for safety

    //     // If you're getting store_id from the authenticated user:
    //     // $store_id = auth()->user()->store_id; // Example if using Laravel's auth

    //     if (!$store_id) {
    //         return response()->json(['error' => 'Store ID is required.'], 400);
    //     }

    //     // Get the year from the request, or default to the current year
    //     $year = $request->input('year', Carbon::now()->year);

    //     // Define the start and end dates for the specified year
    //     $startDate = Carbon::create($year, 1, 1)->startOfDay();
    //     $endDate = Carbon::create($year, 12, 31)->endOfDay();

    //     // Query to get total sales per month for the specified year
    //     $sales = Sale::whereBetween('date', [$startDate, $endDate])
    //         ->selectRaw('MONTH(date) as month_number, SUM(selling_price) as total_profit')
    //         ->where('store_id', $store_id)
    //         ->groupBy('month_number')
    //         ->orderBy('month_number')
    //         ->get();

    //     // Initialize an array to hold total sales for all 12 months, defaulting to 0
    //     // Month numbers are 1-12
    //     $monthlyProfits = array_fill(1, 12, 0.0);

    //     // Populate the monthlyProfits array with actual data from the query results
    //     foreach ($sales as $sale) {
    //         $month = (int) $sale->month_number;
    //         $monthlyProfits[$month] = (float) $sale->total_profit;
    //     }

    //     // Define month labels corresponding to month numbers 1-12
    //     $monthLabels = [
    //         'January', 'February', 'March', 'April', 'May', 'June',
    //         'July', 'August', 'September', 'October', 'November', 'December'
    //     ];

    //     // Format the response to match the frontend's `filterDataByYear` expectation
    //     // which rebuilds the data for all 12 months.
    //     // So, we'll send an array of objects for the *current selected year*.
    //     $response = [];
    //     for ($i = 0; $i < 12; $i++) {
    //         $monthName = $monthLabels[$i];
    //         $profit = $monthlyProfits[$i + 1]; // +1 because monthlyProfits is 1-indexed

    //         $response[] = [
    //             'year' => (int) $year, // Ensure year is an integer
    //             'month' => $monthName,
    //             'profit' => $profit
    //         ];
    //     }

    //     return response()->json($response, 200);
    // }



    public function cashierSale(Request $request)
    {

        $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }

        $user_id = $userData['id'];
        $userRole = $userData['role']; // Get role from decoded user data

        // Check if the user exists in the database
        $userCheck = User::find($user_id);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Authorization: Check if the user has the 'admin' role
    
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'stock_id' => 'required|exists:stocks,id',
            'quantity' => 'required|integer|min:1',
            'selling_price' => 'required|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_contact' => 'nullable|integer', // Reverted to nullable integer
            'payment_method' => 'required|string|in:cash,mobile',
            'receipt_code' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Sale validation failed: ' . json_encode($validator->errors()));
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();



        // Extract validated data
        $store_id = $data['store_id'];
        $product_id = $data['product_id'];
        $initial_stock_id = $data['stock_id'];
        $quantity_requested = $data['quantity'];
        $selling_price = $data['selling_price'];
        $customer_name = $data['customer_name'] ?? 'Anonymous';
        // If customer_contact is nullable integer, it will be null if not provided
        $customer_contact = $data['customer_contact']; 
        $payment_method = $data['payment_method'];
        $receipt_code = $data['receipt_code'];
        $date = Carbon::now()->toDateString();

        // Fetch product for error messages
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        // 3. Stock Deduction Logic
        $stock_item_to_deduct = null;

        // Try to use the initially selected stock_id first
        $initial_stock = Stock::where('id', $initial_stock_id)
                              ->where('product_id', $product_id)
                              ->where('store_id', $store_id)
                              ->first();

        if ($initial_stock && $initial_stock->quantity >= $quantity_requested) {
            $stock_item_to_deduct = $initial_stock;
        } else {
            // Look for other suitable stock batches
            $alternative_stocks = Stock::where('product_id', $product_id)
                                       ->where('store_id', $store_id)
                                       ->where('quantity', '>', 0)
                                       ->orderByDesc('quantity')
                                       ->get();

            foreach ($alternative_stocks as $alt_stock) {
                if ($alt_stock->quantity >= $quantity_requested) {
                    $stock_item_to_deduct = $alt_stock;
                    break;
                }
            }
        }

        // Check if a suitable stock item was found
        if (!$stock_item_to_deduct) {
            $total_available_stock = Stock::where('product_id', $product_id)
                                          ->where('store_id', $store_id)
                                          ->sum('quantity');

            return response()->json([
                'error' => 'Insufficient stock for product "' . $product->name . '". ' .
                           'Requested: ' . $quantity_requested . '. ' .
                           'Total available across all batches: ' . $total_available_stock . '.',
                'quantity_available' => $total_available_stock
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 4. Create Sale Transaction and Deduct Stock
        try {
            // Optional: Start a database transaction for atomicity
            // \DB::beginTransaction();

            $total_price = $quantity_requested * $selling_price;

            $sale = Sale::create([
                'product_id' => $product_id,
                'stock_id' => $stock_item_to_deduct->id,
                'store_id' => $store_id,
                'user_id' => $user_id,
                'quantity' => $quantity_requested,
                'date' => $date,
                'selling_price' => $selling_price,
                'total_price' => $total_price,
                'customer_id' => 0,
                'customer_name' => $customer_name,
                'customer_contact' => $customer_contact, // Use integer value
                'payment_method' => $payment_method,
                'receipt_code' => $receipt_code,
                'status' => 1,
            ]);

            $stock_item_to_deduct->last_quantity = $stock_item_to_deduct->quantity;
            $stock_item_to_deduct->quantity -= $quantity_requested;
            $stock_item_to_deduct->save();

            // 5. Handle Customer (Find or Create)
            $customer = null;
            if ($customer_name !== 'Anonymous') {
                $customer = Customer::firstOrCreate(
                    ['name' => $customer_name, 'store_id' => $store_id],
                    [
                        'contact' => $customer_contact, // Use integer value
                        'user_id' => $user_id,
                        'status' => 1
                    ]
                );
            }

            if ($customer) {
                $sale->customer_id = $customer->id;
                $sale->save();
            }

            // Optional: Commit the transaction
            // \DB::commit();

            // 6. Return Success Response
            return response()->json([
                'success' => 'Sale transaction stored and stock deducted successfully!',
                'sale' => $sale,
                'stock_updated' => $stock_item_to_deduct,
                'quantity_remaining' => $stock_item_to_deduct->quantity
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            // Optional: Rollback the transaction on error
            // \DB::rollBack();
            Log::error('Error during sale transaction: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);
            return response()->json(['error' => 'An internal server error occurred while processing the sale.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAvailableYears(Request $request)
    {
        $storeData = json_decode($request->input('store', '{}'), true);
        $store_id = $storeData['id'] ?? null;

        // Query the 'sales' table for distinct years
        $query = Sale::select(DB::raw('DISTINCT YEAR(date) as year'))
                     ->orderBy('year', 'asc');

        if ($store_id) {
            $query->where('store_id', $store_id);
        }

        $years = $query->pluck('year')->toArray();

        // If no years are found (e.g., no sales yet), include the current year as a fallback
        if (empty($years)) {
            $years[] = Carbon::now()->year;
        }

        return response()->json($years);
    }


    // statistics 

    private function applyFilters($query, array $filters)
    {
        // Filter by store
        if (isset($filters['store'])) {
            $storeData = json_decode($filters['store'], true);
            $store_id = $storeData['id'] ?? null;
            if ($store_id) {
                $query->where('sales.store_id', $store_id);
            }
        }
        return $query;
    }
   

    // public function totalSalePerPeriod(Request $request)
    // {
    //     $query = Sale::query();
    //     $query = $this->applyFilters($query, $request->all());

    //     $totalSalePerPeriod = $query->sum('selling_price');

    //     return response()->json(['totalSalePerPeriod' => $totalSalePerPeriod]);
    // }

    /**
     * Get the count of total sales transactions for a given period.
     * Endpoint: POST /api/sales/totalsalecountperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalSaleCountPerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'store' => 'required|json', // Store is typically required for sales analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . (Carbon::now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        // 2. Start the query with the Sale model.
        $query = Sale::query();

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
        // and the count will cover all sales for the selected store.

        // 5. Apply the determined date range to the query if one was set.
        if ($startDate && $endDate) {
            $query->whereBetween('sales.date', [$startDate, $endDate]);
        }

        // 6. Use count() to get the number of sale records (transactions) for the filtered period.
        $totalSaleCount = $query->distinct('receipt_code')->count();

        // 7. Return the calculated total sales count as a JSON response.
        return response()->json(['totalSaleCount' => $totalSaleCount], Response::HTTP_OK);
    }


    /**
     * Get total sales amount for a given period.
     * Endpoint: POST /api/sales/totalsaleperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function totalSalePerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $request->validate([
            'store' => 'required|json', // Store is typically required for sales analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        // 2. Start the query with the Sale model.
        $query = Sale::query();

        // 3. Apply common filters (store, date range: year, month, week, start_date, end_date).
        // The applyFilters method will automatically apply the appropriate WHERE clauses
        // to 'sales.date' and 'sales.store_id'.
        $query = $this->applyFilters($query, $request->all());

        // 4. Sum the total sales for the *entire* filtered period.
        // Based on your other functions (like profit calculation), we assume total sale
        // is calculated as (selling_price * quantity).
        // Using DB::raw() ensures the calculation happens directly in the SQL query.
        $totalSalePerPeriod = $query->sum(DB::raw('selling_price * quantity'));

        // 5. Return the calculated total sales revenue as a JSON response.
        return response()->json(['totalSalePerPeriod' => (float) $totalSalePerPeriod]); // Cast to float for clear numeric output
    }
    /**
     * Get total profit for a given period.
     * Endpoint: POST /api/sales/totalprofitperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalProfitPerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $request->validate([
            'store' => 'required|json', // Store is typically required for financial analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        // 2. Start the query: Begin with the Sale model and join with the Stock model.
        // This join is necessary to access 'stocks.cost_price' for profit calculation.
        // We assume 'sales.stock_id' links to 'stocks.id'.
        $query = Sale::query()
                     ->join('stocks', 'sales.stock_id', '=', 'stocks.id');

        // 3. Apply common filters (store, date range: year, month, week, start_date, end_date)
        // The applyFilters method will automatically apply the appropriate WHERE clauses
        // to 'sales.date' and 'sales.store_id'.
        $query = $this->applyFilters($query, $request->all());

        // 4. Calculate the total profit for the *entire* filtered period.
        // We use DB::raw() to perform the arithmetic calculation directly in the SQL query.
        $totalProfit = $query->sum(DB::raw('(sales.selling_price - stocks.cost_price) * sales.quantity'));

        // 5. Return the calculated total profit as a JSON response.
        return response()->json(['total_profit' => (float) $totalProfit]); // Cast to float for clear numeric output
    }
    /**
     * Get sales summary data for charting (e.g., sales per day/month).
     * Endpoint: POST /api/sales/salesummaryperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    // Assuming this is within a controller that has the applyFilters method
    // E.g., class SalesController extends Controller { ... }
    public function salesSummaryPerPeriod(Request $request)
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

        // Start with Sale query
        $salesQuery = Sale::query();

        // Apply common filters, specifically the store filter.
        $salesQuery = $this->applyFilters($salesQuery, $request->all());

        $yearInput = $request->input('year');
        $monthInput = $request->input('month');
        $weekInput = $request->input('week');

        // Condition 1: If a SPECIFIC WEEK is selected (Highest Priority - Daily Sales for that week)
        if ($weekInput) {
            $isoYear = $yearInput ?? Carbon::now()->year;

            $startDate = (new Carbon())->setISODate($isoYear, $weekInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endDate = $startDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Explicitly apply the specific week's date range to the query
            $salesQuery->whereBetween('sales.date', [$startDate, $endDate]);

            $salesQuery->selectRaw("DATE_FORMAT(sales.date, '%Y-%m-%d') as sale_date, SUM(selling_price * quantity) as total_sales");
            $salesQuery->groupBy('sale_date');
            $salesQuery->orderBy('sale_date');

            $results = $salesQuery->get();

            $dailySalesData = [];
            foreach ($results as $row) {
                $dailySalesData[$row->sale_date] = (float) $row->total_sales;
            }

            $orderedLabels = [];
            $orderedData = [];

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
                $orderedData[] = $dailySalesData[$dateString] ?? 0.0;
                $currentDate->addDay();
            }

            $labels = $orderedLabels;
            $data = $orderedData;
        }
        // Condition 2: Else if a MONTH and YEAR are selected (Weekly Sales within Month)
        elseif ($monthInput && $yearInput) {
            $startDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->startOfDay();
            $endDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->endOfMonth()->endOfDay();

            // Explicitly limit the sales query to the selected month for aggregation
            $salesQuery->whereBetween('sales.date', [$startDateOfMonth, $endDateOfMonth]);

            $salesQuery->selectRaw("DATE_FORMAT(sales.date, '%x%v') as period_key, SUM(selling_price * quantity) as total");
            $salesQuery->groupBy('period_key');
            $salesQuery->orderBy('period_key');

            $results = $salesQuery->get();

            $tempWeeklyData = [];
            foreach ($results as $row) {
                $tempWeeklyData[(int) $row->period_key] = (float) $row->total;
            }

            $rawWeekData = [];
            $processedPeriodKeys = [];

            $currentDayInLoop = $startDateOfMonth->copy();
            while ($currentDayInLoop->lte($endDateOfMonth)) {
                $weekNum = (int) $currentDayInLoop->format('W');
                $isoYear = (int) $currentDayInLoop->format('o');

                $periodKey = (int) ($isoYear . sprintf('%02d', $weekNum));

                if (!isset($processedPeriodKeys[$periodKey])) {
                    $processedPeriodKeys[$periodKey] = true;

                    $weekStartDate = (new Carbon())->setISODate($isoYear, $weekNum)->startOfWeek(Carbon::MONDAY);
                    $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

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
                $currentDayInLoop->addDay();
            }

            usort($rawWeekData, function($a, $b) {
                return $a['iso_period_key'] <=> $b['iso_period_key'];
            });

            foreach ($rawWeekData as $item) {
                $labels[] = $item['label'];
                $data[] = $item['data'];
            }
        }
        // Condition 3: Else if ONLY a YEAR is selected (Monthly Sales)
        elseif ($yearInput) {
            $startDateOfYear = Carbon::createFromDate($yearInput, 1, 1)->startOfDay();
            $endDateOfYear = Carbon::createFromDate($yearInput, 12, 31)->endOfDay();
            $salesQuery->whereBetween('sales.date', [$startDateOfYear, $endDateOfYear]);

            $salesQuery->selectRaw('MONTH(sales.date) as period_key, SUM(selling_price * quantity) as total_sales');
            $salesQuery->groupBy('period_key');
            $salesQuery->orderBy('period_key');

            $results = $salesQuery->get();

            for ($m = 1; $m <= 12; $m++) {
                $monthLabel = Carbon::create(null, $m, 1)->translatedFormat('M');
                // Apply ucfirst for French locale
                if ($locale === 'fr') {
                    $labels[] = ucfirst($monthLabel);
                } else {
                    $labels[] = $monthLabel;
                }
            }
            $tempData = array_fill(1, 12, 0.0);
            foreach ($results as $row) {
                $tempData[(int) $row->period_key] = (float) $row->total_sales;
            }
            $data = array_values($tempData);
        }
        // Default Condition (Fallback): Show ALL years of sales (Yearly Sales from first sale)
        else {
            $firstSaleRecord = Sale::when($store_id, function ($query) use ($store_id) {
                return $query->where('store_id', $store_id);
            })->orderBy('date', 'asc')->first();

            $startHistoricalYear = $firstSaleRecord ? Carbon::parse($firstSaleRecord->date)->year : Carbon::now()->subYears(5)->year;
            $currentYear = Carbon::now()->year;

            if ($startHistoricalYear > $currentYear) {
                $startHistoricalYear = $currentYear;
            }

            $salesQuery->whereBetween('sales.date', [
                Carbon::createFromDate($startHistoricalYear, 1, 1)->startOfDay(),
                Carbon::createFromDate($currentYear, 12, 31)->endOfDay()
            ]);

            $salesQuery->selectRaw('YEAR(sales.date) as period_key, SUM(selling_price * quantity) as total_sales');
            $salesQuery->groupBy('period_key');
            $salesQuery->orderBy('period_key');

            $results = $salesQuery->get();

            $tempData = array_fill_keys(range($startHistoricalYear, $currentYear), 0.0);
            $labels = array_map('strval', range($startHistoricalYear, $currentYear)); // Years are numbers, no translation needed

            foreach ($results as $row) {
                if (isset($tempData[(int) $row->period_key])) {
                    $tempData[(int) $row->period_key] = (float) $row->total_sales;
                }
            }
            $data = array_values($tempData);
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ], Response::HTTP_OK);
    }
    /**
     * Get profit summary data for charting (e.g., profit per day/month).
     * Endpoint: POST /api/sales/profitsummaryperperiod
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    // Assuming this is within a controller that has the applyFilters method
// E.g., class ProfitController extends Controller { ... }

    public function profitSummaryPerPeriod(Request $request)
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

        // Start with Sale query and join Stock for cost_price
        $profitQuery = Sale::query()->join('stocks', 'sales.stock_id', '=', 'stocks.id');

        // Apply common filters, specifically the store filter.
        $profitQuery = $this->applyFilters($profitQuery, $request->all());

        $yearInput = $request->input('year');
        $monthInput = $request->input('month');
        $weekInput = $request->input('week');

        // Determine the ISO year for the week. If not provided, assume current year.
        $isoYear = $yearInput ?? Carbon::now()->year;

        // Condition 1: If a SPECIFIC WEEK is selected (Highest Priority - Daily Profit for that week)
        // We derive the exact start/end dates for the week directly here.
        if ($weekInput) {
            $startDate = (new Carbon())->setISODate($isoYear, $weekInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endDate = $startDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Explicitly apply the specific week's date range to the query
            $profitQuery->whereBetween('sales.date', [$startDate, $endDate]);

            $profitQuery->selectRaw("DATE_FORMAT(sales.date, '%Y-%m-%d') as sale_date, SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit");
            $profitQuery->groupBy('sale_date');
            $profitQuery->orderBy('sale_date');

            $results = $profitQuery->get();

            $dailyProfitData = [];
            foreach ($results as $row) {
                $dailyProfitData[$row->sale_date] = (float) $row->total_profit;
            }

            $orderedLabels = [];
            $orderedData = [];

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
                $orderedData[] = $dailyProfitData[$dateString] ?? 0.0;
                $currentDate->addDay();
            }

            $labels = $orderedLabels;
            $data = $orderedData;
        }
        // Condition 2: Else if a MONTH and YEAR are selected (Weekly Profit within Month)
        elseif ($monthInput && $yearInput) {
            $startDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->startOfDay();
            $endDateOfMonth = Carbon::createFromDate($yearInput, $monthInput, 1)->endOfMonth()->endOfDay();

            // Explicitly limit the profit query to the selected month for aggregation
            $profitQuery->whereBetween('sales.date', [$startDateOfMonth, $endDateOfMonth]);

            $profitQuery->selectRaw("DATE_FORMAT(sales.date, '%x%v') as period_key, SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit");
            $profitQuery->groupBy('period_key');
            $profitQuery->orderBy('period_key');

            $results = $profitQuery->get();

            $tempWeeklyData = [];
            foreach ($results as $row) {
                $tempWeeklyData[(int) $row->period_key] = (float) $row->total_profit;
            }

            $rawWeekData = [];
            $processedPeriodKeys = [];

            $currentDayInLoop = $startDateOfMonth->copy();
            while ($currentDayInLoop->lte($endDateOfMonth)) {
                $weekNum = (int) $currentDayInLoop->format('W');
                $isoYear = (int) $currentDayInLoop->format('o');

                $periodKey = (int) ($isoYear . sprintf('%02d', $weekNum));

                if (!isset($processedPeriodKeys[$periodKey])) {
                    $processedPeriodKeys[$periodKey] = true;

                    $weekStartDate = (new Carbon())->setISODate($isoYear, $weekNum)->startOfWeek(Carbon::MONDAY);
                    $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

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
                $currentDayInLoop->addDay();
            }

            usort($rawWeekData, function($a, $b) {
                return $a['iso_period_key'] <=> $b['iso_period_key'];
            });

            foreach ($rawWeekData as $item) {
                $labels[] = $item['label'];
                $data[] = $item['data'];
            }
        }
        // Condition 3: Else if ONLY a YEAR is selected (Monthly Profit)
        elseif ($yearInput) {
            $startDateOfYear = Carbon::createFromDate($yearInput, 1, 1)->startOfDay();
            $endDateOfYear = Carbon::createFromDate($yearInput, 12, 31)->endOfDay();
            $profitQuery->whereBetween('sales.date', [$startDateOfYear, $endDateOfYear]);

            $profitQuery->selectRaw('MONTH(sales.date) as period_key, SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit');
            $profitQuery->groupBy('period_key');
            $profitQuery->orderBy('period_key');

            $results = $profitQuery->get();

            for ($m = 1; $m <= 12; $m++) {
                $monthLabel = Carbon::create(null, $m, 1)->translatedFormat('M');
                // Apply ucfirst for French locale
                if ($locale === 'fr') {
                    $labels[] = ucfirst($monthLabel);
                } else {
                    $labels[] = $monthLabel;
                }
            }
            $tempData = array_fill(1, 12, 0.0);
            foreach ($results as $row) {
                $tempData[(int) $row->period_key] = (float) $row->total_profit;
            }
            $data = array_values($tempData);
        }
        // Default Condition (Fallback): Show ALL years of sales (Yearly Profit from first sale)
        else {
            $firstSaleRecord = Sale::when($store_id, function ($query) use ($store_id) {
                return $query->where('store_id', $store_id);
            })->orderBy('date', 'asc')->first();

            $startHistoricalYear = $firstSaleRecord ? Carbon::parse($firstSaleRecord->date)->year : Carbon::now()->subYears(5)->year;
            $currentYear = Carbon::now()->year;

            if ($startHistoricalYear > $currentYear) {
                $startHistoricalYear = $currentYear;
            }

            $profitQuery->whereBetween('sales.date', [
                Carbon::createFromDate($startHistoricalYear, 1, 1)->startOfDay(),
                Carbon::createFromDate($currentYear, 12, 31)->endOfDay()
            ]);

            $profitQuery->selectRaw('YEAR(sales.date) as period_key, SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit');
            $profitQuery->groupBy('period_key');
            $profitQuery->orderBy('period_key');

            $results = $profitQuery->get();

            $tempData = array_fill_keys(range($startHistoricalYear, $currentYear), 0.0);
            $labels = array_map('strval', range($startHistoricalYear, $currentYear));

            foreach ($results as $row) {
                if (isset($tempData[(int) $row->period_key])) {
                    $tempData[(int) $row->period_key] = (float) $row->total_profit;
                }
            }
            $data = array_values($tempData);
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ], Response::HTTP_OK);
    }
    /**
     * Get the most sold products for a given period.
     * Endpoint: POST /api/sales/mostsoldproduct
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mostSoldProductPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'store' => 'required|json', // Store is typically required for product analytics
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . (Carbon::now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        // 2. Start the query: Join the 'sales' table with the 'products' table.
        //    We assume 'sales.product_id' links to 'products.id'.
        $query = Sale::query()
                      ->join('products', 'sales.product_id', '=', 'products.id');

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
        // and the query will consider all sales for the selected store.

        // 5. Apply the determined date range to the query if one was set.
        if ($startDate && $endDate) {
            $query->whereBetween('sales.date', [$startDate, $endDate]);
        }

        // 6. Select product details and aggregate sales quantity
        $mostSold = $query->select(
                                'products.id', // Include ID for unique grouping (good practice)
                                'products.name', // Select the name of the product
                                DB::raw('SUM(sales.quantity) as total_sold') // Sum the quantity sold for each product
                            )
                            // 7. Group by product to correctly aggregate quantities
                            // Grouping by both ID and name is a good practice to avoid ambiguity if names aren't unique.
                            ->groupBy('products.id', 'products.name')
                            // 8. Order the results by the total quantity sold in descending order
                            ->orderByDesc('total_sold')
                            // 9. Limit the results to the top N products (e.g., top 5)
                            ->limit(5)
                            // 10. Execute the query and get the results
                            ->get();

        // 11. Return the results as a JSON response
        return response()->json(['mostSold' => $mostSold], Response::HTTP_OK);
    }


    /**
     * Get the top customers for a given period.
     * Endpoint: POST /api/sales/topcustomer
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
      public function topCustomerPeriod(Request $request)
    {
        // 1. Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'store' => 'required|json', // Store is now explicitly required for this endpoint
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . (Carbon::now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'week' => 'nullable|integer|min:1|max:53',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        // 2. Start the query with the Sale model.
        $query = Sale::query();
        $selectNameColumn = '';
        $groupByColumns = [];

        // 3. Determine how to get customer name based on your schema
        // Option 1: Sales link to a separate 'customers' table (recommended for normalized data)
        if (Schema::hasTable('customers') && Schema::hasColumn('sales', 'customer_id')) {
            $query->join('customers', 'sales.customer_id', '=', 'customers.id');
            $selectNameColumn = 'customers.name';
            $groupByColumns = ['customers.id', 'customers.name']; // Group by ID and name for uniqueness
        }
        // Option 2: Customer name is directly stored on the 'sales' table (less normalized, but common for quick solutions)
        elseif (Schema::hasColumn('sales', 'customer_name')) {
            $selectNameColumn = 'sales.customer_name';
            $groupByColumns = ['sales.customer_name'];
        }
        else {
            // Fallback: If no identifiable customer column, return an error or empty response.
            // Log a warning for debugging purposes.
            Log::warning('topCustomerPeriod: No customer identification column found on sales table or related customers table. Check your database schema.');
            return response()->json(['topCustomers' => []], Response::HTTP_INTERNAL_SERVER_ERROR); // Return 500 if critical schema is missing
        }

        // 4. Apply store filter.
        $query = $this->applyFilters($query, $request->all());

        // 5. Determine the date range based on user input, with priority given to more specific periods.
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
        // and the query will consider all sales for the selected store.

        // 6. Apply the determined date range to the query if one was set.
        if ($startDate && $endDate) {
            $query->whereBetween('sales.date', [$startDate, $endDate]);
        }

        // 7. Select the customer name and aggregate sales data.
        // The current aggregation is by 'total_bought' (SUM of sales.quantity).
        // If you want top customers by total money spent, change 'SUM(sales.quantity)'
        // to 'SUM(sales.quantity * sales.selling_price)'.
        $topCustomers = $query->select(
                                    DB::raw("{$selectNameColumn} as name"),
                                    DB::raw('SUM(sales.quantity) as total_bought') // Or SUM(sales.quantity * sales.selling_price) as total_spent
                                )
                                ->groupBy($groupByColumns)
                                ->orderByDesc('total_bought') // Order by total items bought
                                ->limit(5) // Limit to top 5 customers
                                ->get();

        // 8. Return the results as a JSON response
        return response()->json(['topCustomers' => $topCustomers], Response::HTTP_OK);
    }

    // No filter 

    public function totalSaleCountAll(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $totalSaleCountAll = Sale::where('store_id', $store_id)->count();

        $status = 200;
        $response = [
            'success'=> true,
            'totalSaleCountAll'=> $totalSaleCountAll
        ];

        return response()->json($response, $status);
    }

    public function totalProfitAll(Request $request)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];
      
       
        $totalProfit = Sale::where('sales.store_id', $store_id)
            ->leftJoin('stocks', 'sales.stock_id', '=', 'stocks.id')
            ->selectRaw('SUM((sales.selling_price - stocks.cost_price) * sales.quantity) as total_profit')
            ->value('total_profit'); // Use value() to get a single aggregated result directly

        // If no sales or no profit, value() will return null, so default to 0.0

        return response()->json([
            'success' => true,
            'totalProfitAll' => $totalProfit
        ], 200);

        return response()->json($response, $status);
    }




    // send mail

    public function sendReceiptEmail(Request $request)
    {
        // Get the locale from the Accept-Language header, default to 'en' if not present
        // This should be done *before* validation if validation messages should also be localized
        $locale = $request->header('Accept-Language', 'en');

        // Set the application locale for this request context
        App::setLocale($locale);

        // 1. Validate incoming data
        // Validation messages will now automatically respect the 'locale' set above
        $validatedData = $request->validate([
            'to_email' => 'required|email',
            'receipt_id' => 'required|string',
            'customer_name' => 'nullable|string',
            'cashier_name' => 'required|string',
            'sale_date' => 'required|string', // Consider using date format validation, e.g., 'date_format:Y-m-d'
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'store_name' => 'required|string',
            'store_location' => 'nullable|string',
            // Changed from integer to string for 'store_contact' as phone numbers can contain non-numeric chars or be sent as strings
            // If you expect only numbers, keep 'integer' but ensure frontend sends it as such.
            'store_contact' => 'nullable|integer',
            'store_logo_url' => 'nullable|url', // Validate if it's a URL
        ]);

        try {
            // 2. Prepare data for the email
            $details = [
                'receipt_id' => $validatedData['receipt_id'],
                'customer_name' => $validatedData['customer_name'] ?? 'Anonymous',
                'cashier_name' => $validatedData['cashier_name'],
                'sale_date' => $validatedData['sale_date'],
                'items' => $validatedData['items'],
                'total_amount' => $validatedData['total_amount'],
                'store_name' => $validatedData['store_name'],
                'store_location' => $validatedData['store_location'],
                'store_contact' => $validatedData['store_contact'],
                'store_logo_url' => $validatedData['store_logo_url'],
            ];

              Log::info('Store logo URL for email: ' . ($details['store_logo_url'] ?? 'null'));
            // 3. Send the email
            // The Mailable's build method will now use the locale set by App::setLocale($locale)
            Mail::to($validatedData['to_email'])->send(new SaleReceiptMail($details));

            Log::info("Receipt email sent successfully to {$validatedData['to_email']} for sale {$validatedData['receipt_id']} with locale: {$locale}");
            return response()->json(['success' => true, 'message' => 'Receipt email sent.']);

        } catch (\Exception $e) {
            Log::error("Failed to send receipt email to {$validatedData['to_email']} for sale {$validatedData['receipt_id']}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send receipt email.', 'error' => $e->getMessage()], 500);
        }
    }





    // admin functions

    public function adminSalePerWeek(Request $request)
    {
        // 1. User Authentication & Authorization Check
        // It's generally better to use Laravel's built-in authentication and authorization
        // features (like Auth::user() and policies/gates) rather than passing user data in the request body.
        // However, adapting to your current structure for now:

        $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }

        $userId = $userData['id'];
        $userRole = $userData['role']; // Get role from decoded user data

        // Check if the user exists in the database
        $userCheck = User::find($userId);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Authorization: Check if the user has the 'admin' role
        $adminRole = "admin"; // Define role string once
        if ($userRole !== $adminRole) { // Use strict comparison !==
            return response()->json(['error' => 'You are not authorized to do this operation']); 
        }

        // 2. Date Calculation for Current Week
        // Carbon's startOfWeek and endOfWeek are inclusive and reliable.
        // Carbon::MONDAY is a constant for clarity.
        // If your week starts on Sunday, use Carbon::SUNDAY.

        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0); // Start of Monday (00:00:00)
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY)->setTime(23, 59, 59); // End of Sunday (23:59:59)

        // For "current week" starting Monday and ending 7 days later (Sunday):
        // $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        // $endDate = $startDate->copy()->addDays(6)->endOfDay(); // 6 days after Monday is Sunday, end of day

        // 3. Database Query
        // Use get() to execute the query and retrieve a Collection of models.
        // all() is not a valid method on a Query Builder instance.
        $stores = Sale::whereBetween('created_at', [$startDate, $endDate])->get();

        // 4. Response Handling
        // Always return a JSON response with appropriate status codes.

        if ($stores->isNotEmpty()) { // Check if the collection is not empty
            return response()->json([
                'success' => 'Sales retrieved successfully.', // More descriptive success message
                'sales' => $stores,
            ], 200); // OK
        } else {
            // If no stores are found for the week, it's not an "error, failed to list stores"
            // it's just that there are no stores. A 200 OK with an empty array or a specific message is better.
            return response()->json([
                'message' => 'No sale found for the current week.',
                'sales' => [], // Return an empty array
              
            ], 200); // Still 200 OK if the operation was successful but yielded no results
        }
    }
}
