<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Customer;
use App\Models\Store;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

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

    public function store(Request $request){

        $validator = Validator::make($request->all(), [

            'product_id' => 'required|exists:products,id',
            'stock_id' => 'required|exists:stocks,id',
            'quantity' => 'required|integer',
            'selling_price' => 'required|integer|min:0',
            'customer_name' => 'required|string',
            'customer_contact' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeSaleData = $validator->validated();
        $product_id = $storeSaleData['product_id'];
        $stock_id = $storeSaleData['stock_id'];
        $quantity = $storeSaleData['quantity'];
        $date = now()->toDateString();
        $selling_price = $storeSaleData['selling_price'];
        $total_price = $quantity * $selling_price;
        $customer_name = $storeSaleData['customer_name'];
        $customer_contact = $storeSaleData['customer_contact'];


        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $product = Product::find($product_id);
        $stock_check = Stock::where('product_id', $product_id)->where('store_id', $store_id)->where('id', $stock_id)->first();
        
        $currentqtty = $stock_check->quantity;

        if($quantity > $currentqtty)
        {

            // $status = 400;
            $response = [
                'error' => 'The quantity demanded is greater than the quantity in stock!',
                'quantity_remaining' => $currentqtty
            ];

        } else if($quantity == $currentqtty){

            // $status = 400;
            $response = [
                'error' => 'The quantity demanded is the exact quantity in stock',
                'quantity_remaining' => $currentqtty
            ];
        } else if($currentqtty <= 1){

            // $status = 400;
            $response = [
                'error' => 'The stock is in shortage',
                'quantity_remaining' => $currentqtty
            ];
        
        } else {

            $sale = Sale::create([

                'product_id' => $storeSaleData['product_id'],
                'stock_id' => $storeSaleData['stock_id'],
                'store_id' => $store_id,
                'user_id' => $user_id,
                'quantity' => $storeSaleData['quantity'],
                'date' => $date,
                'selling_price' => $storeSaleData['selling_price'],
                'total_price' => $total_price,
                'customer_id' => 0,
                'customer_name' => $storeSaleData['customer_name'],
                'customer_contact' => $storeSaleData['customer_contact'],
                'status' => 1,
                

            ]);

            if($sale){

                $currentqtty = $stock_check->quantity;
                $stock_check->last_quantity = $currentqtty;
                $newqtty = $currentqtty - $quantity;
                $stock_check->quantity = $newqtty;

                if($stock_check->save()){

                    $status = 201;
                    $response = [
                        'success' => 'Sale transaction stored and stock deduced successfully!',
                        'sale' => $sale,
                        'stock' => $stock_check,
                        'quantity_remaining' => $newqtty
                    ];

                    $customer_check = Customer::where('name', $customer_name)->where('store_id', $store_id)->first();

                    if(!$customer_check)
                    {
                        $customer = Customer::create([

                            'name' => $customer_name,
                            'contact' => $customer_contact,
                            'store_id' => $store_id,
                            'user_id' => $user_id,
                            'status' => 1
            
                        ]);

                        $latest_customer = Customer::where('store_id', $store_id)->orderBy('id', 'desc')->value('id');
                        $latest_sale = Sale::where('store_id', $store_id)->orderBy('id', 'desc')->first();
                        
                        if($latest_customer && $latest_sale){
                            $latest_sale->customer_id = $latest_customer;
                            $latest_sale->save();
                        }
                    } 

                } else {

                    // $status = 422;
                    $response = [
                        'error' => 'Error, Sale transaction saved but stock nor deduce!',
                    ];

                }

            } else {

                // $status = 422;
                $response = [
                    'error' => 'Error, failed to store sale transaction  !',
                ];

            }

        }

        return response()->json($response);

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
        ->limit(4)
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
        ->limit(4)
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
        $totalSalePerDay = Sale::where('store_id', $store_id)->where('date', $date)->count();

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
        ->selectRaw('DAYOFWEEK(date) as weekday, SUM(selling_price) as total')
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

    public function cashierSale(Request $request){

        $validator = Validator::make($request->all(), [

            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'stock_id' => 'required|exists:stocks,id',
            'quantity' => 'required|integer',
            'selling_price' => 'required|integer|min:0',
            'customer_name' => 'required|string',
            'customer_contact' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeSaleData = $validator->validated();
        $store_id = $storeSaleData['store_id'];
        $product_id = $storeSaleData['product_id'];
        $stock_id = $storeSaleData['stock_id'];
        $quantity = $storeSaleData['quantity'];
        $date = now()->toDateString();
        $selling_price = $storeSaleData['selling_price'];
        $total_price = $quantity * $selling_price;
        $customer_name = $storeSaleData['customer_name'];
        $customer_contact = $storeSaleData['customer_contact'];


        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];


        $product = Product::find($product_id);
        $stock_check = Stock::where('product_id', $product_id)->where('store_id', $store_id)->where('id', $stock_id)->first();
        
        $currentqtty = $stock_check->quantity;

        if($quantity > $currentqtty)
        {

            // $status = 400;
            $response = [
                'error' => 'The quantity demanded is greater than the quantity in stock!',
                'quantity_remaining' => $currentqtty
            ];

        } else if($quantity == $currentqtty){

            // $status = 400;
            $response = [
                'error' => 'The quantity demanded is the exact quantity in stock',
                'quantity_remaining' => $currentqtty
            ];
        } else if($currentqtty <= 1){

            // $status = 400;
            $response = [
                'error' => 'The stock is in shortage',
                'quantity_remaining' => $currentqtty
            ];
        
        } else {

            $sale = Sale::create([

                'product_id' => $storeSaleData['product_id'],
                'stock_id' => $storeSaleData['stock_id'],
                'store_id' => $store_id,
                'user_id' => $user_id,
                'quantity' => $storeSaleData['quantity'],
                'date' => $date,
                'selling_price' => $storeSaleData['selling_price'],
                'total_price' => $total_price,
                'customer_id' => 0,
                'customer_name' => $storeSaleData['customer_name'],
                'customer_contact' => $storeSaleData['customer_contact'],
                'status' => 1,
                

            ]);

            if($sale){

                $currentqtty = $stock_check->quantity;
                $stock_check->last_quantity = $currentqtty;
                $newqtty = $currentqtty - $quantity;
                $stock_check->quantity = $newqtty;

                if($stock_check->save()){

                    $status = 201;
                    $response = [
                        'success' => 'Sale transaction stored and stock deduced successfully!',
                        'sale' => $sale,
                        'stock' => $stock_check,
                        'quantity_remaining' => $newqtty
                    ];

                    $customer_check = Customer::where('name', $customer_name)->where('store_id', $store_id)->first();

                    if(!$customer_check)
                    {
                        $customer = Customer::create([

                            'name' => $customer_name,
                            'contact' => $customer_contact,
                            'store_id' => $store_id,
                            'user_id' => $user_id,
                            'status' => 1
            
                        ]);

                        $latest_customer = Customer::where('store_id', $store_id)->orderBy('id', 'desc')->value('id');
                        $latest_sale = Sale::where('store_id', $store_id)->orderBy('id', 'desc')->first();
                        
                        if($latest_customer && $latest_sale){
                            $latest_sale->customer_id = $latest_customer;
                            $latest_sale->save();
                        }
                    } 

                } else {

                    // $status = 422;
                    $response = [
                        'error' => 'Error, Sale transaction saved but stock nor deduce!',
                    ];

                }

            } else {

                // $status = 422;
                $response = [
                    'error' => 'Error, failed to store sale transaction  !',
                ];

            }

        }

        return response()->json($response);

    }
}
