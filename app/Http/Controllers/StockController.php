<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stock;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

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

    public function totalStock(Request $request){
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

}


