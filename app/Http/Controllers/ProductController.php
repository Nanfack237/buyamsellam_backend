<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $products = Product::where('store_id', $store_id)->get();

        if($products){

            $status = 200;
            $response = [
                'success' => 'Product',
                'products' => $products,
            ];
            
        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list products',
            ];

        }

        return response()->json($response, $status);
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'category'=>'required|string',
            'description'=>'required|string',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif',

        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $path = null;

        if($request->hasFile('image')){
            $path = $request->file('image')->store('productImages', 'public');

        }
        
        $storeProductData = $validator->validated();
        $product = Product::create([
            'name' => $storeProductData['name'],
            'category' => $storeProductData['category'],
            'description' => $storeProductData['description'],
            'image'=> $path,
            'store_id'=> $store_id,
            'user_id'=> $user_id,
            'status' => 1
        ]);
        if ($product) {

            $status = 201;
            $response = [
                'success' => 'Product stored successfully !',
                'Product' => $product,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to store the product!',
            ];
        }

        return response()->json($response, $status);

    }//

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'category'=>'required|string',
            'description'=>'required|string',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif',
        ]);

       if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $product = Product::where('store_id', $store_id)->find($id);


        $path = null;

        if($request->hasFile('image')){
            $path = $request->file('image')->store('productImages', 'public');

        }

        $editProductData = $validator->validated(); // Get validated data as array

        $product->name = $editProductData['name'];
        $product->category = $editProductData['category'];
        $product->description = $editProductData['description'];
        $product->image = $path;

        if ($product->save()) {
            $status = 200;
            $response = [
                'message' => 'Product edited successfully!',
                'Product' => $product,
            ];
        } else {
            $status = 422;
            $response = [
                'message' => 'Error, failed to edit the product!',
            ];
        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $product = Product::where('store_id', $store_id)->find($id);

        if($product){
            $status = 200;
            $response = [
                'success' => 'Product',
                'product' => $product,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find product',
            ];
        }


        return response()->json($response, $status);

    }

    public function delete(Request $request, $id)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $product = Product::where('store_id', $store_id)->find($id);

        $hasSales = Sale::where('product_id', $product->id)->where('store_id', $store_id)->exists();
        $hasPurchases = Purchase::where('product_id', $product->id)->where('store_id', $store_id)->exists();

        if ($hasSales || $hasPurchases) {

            $status = 409;
            $response = [
                'error' => 'Cannot delete product: It has associated sales or purchase records.',
            ];
        } else {
       
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
    
            if($product->delete()){
                $status = 200;
                $response = [
                    'success' => 'Product deleted successfully',
                ];
            } else {
                $status = 422;
                $response = [
                    'error' => 'error, failed to delete product',
                ];
            }

        }
        return response()->json($response, $status);
    }
}
