<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule; 
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

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $user = User::find($user_id);
        $userEmail = $user->email;

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'category'=>'required|string',
            'description'=>'required|string',
            'image1'=>'required|image|mimes:jpeg,png,jpg,gif',
            'image2'=>'required|image|mimes:jpeg,png,jpg,gif',

        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }


        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $path1 = null;
        $path2 = null;

        if($request->hasFile('image1') && $request->hasFile('image2')){
            $path1 = $request->file('image1')->store("stores/store_$userEmail/productImages", 'public');
            $path2 = $request->file('image2')->store("stores/store_$userEmail/productImages", 'public');

        }
        
        $storeProductData = $validator->validated();
        $product = Product::create([
            'name' => $storeProductData['name'],
            'category' => $storeProductData['category'],
            'description' => $storeProductData['description'],
            'image1'=> $path1,
            'image2'=> $path2,
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
        // --- 1. Identify User and Store ---
        // It's generally more secure and conventional to get the authenticated user
        // using Auth::user() if your API routes are protected by 'auth:api' middleware.
        // For now, I'll keep your method of decoding 'request->user'.
        $userData = json_decode($request->user, true);

        if (!isset($userData['id'])) {
            return response()->json(['error' => 'User ID is missing from the request.'], 400);
        }
        $userId = $userData['id'];

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        $userEmail = $user->email;

        // Similarly, for store data, it's safer to get the store directly from the authenticated user
        // or ensure the store_id passed in the request genuinely belongs to the user.
        $storeData = json_decode($request->store, true);
        if (!isset($storeData['id'])) {
            return response()->json(['error' => 'Store ID is missing from the request.'], 400);
        }
        $storeId = $storeData['id'];

        // --- 2. Find and Authorize Product ---
        // Find the product that belongs to the identified store, which in turn belongs to the user.
        $product = Product::where('store_id', $storeId)
                          ->find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found or unauthorized for this store.'], 404);
        }

        // --- 3. Validate Request Data ---
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:3|max:255',
            'category'    => 'required|string|max:255',
            'description' => 'nullable|string|min:10',
            'image1'      => 'nullable|image|mimes:jpeg,png,bmp,gif,webp|max:2048',
            'image2'      => 'nullable|image|mimes:jpeg,png,bmp,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            // Use 422 Unprocessable Entity for validation errors
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- 4. Handle Image Uploads and Deletions ---

        // Handle image1
        if ($request->hasFile('image1')) {
            // New image uploaded, delete old one if exists
            if ($product->image1) {
                Storage::disk('public')->delete($product->image1);
            }
            $product->image1 = $request->file('image1')->store("stores/store_$userEmail/productImages", 'public');
        } elseif ($request->input('image1') === 'CLEAR_IMAGE') {
            // Frontend sent 'CLEAR_IMAGE', delete existing image
            if ($product->image1) {
                Storage::disk('public')->delete($product->image1);
            }
            $product->image1 = null; // Set DB field to null
        }
        // If neither a new file nor 'CLEAR_IMAGE' is sent, $product->image1 remains unchanged.

        // Handle image2
        if ($request->hasFile('image2')) {
            if ($product->image2) {
                Storage::disk('public')->delete($product->image2);
            }
            $product->image2 = $request->file('image2')->store("stores/store_$userEmail/productImages/", 'public');
        } elseif ($request->input('image2') === 'CLEAR_IMAGE') {
            if ($product->image2) {
                Storage::disk('public')->delete($product->image2);
            }
            $product->image2 = null;
        }
        // If neither a new file nor 'CLEAR_IMAGE' is sent, $product->image2 remains unchanged.

        // --- 5. Update Other Product Fields ---
        // Fill the product model with validated data directly
        $product->name = $validator->validated()['name'];
        $product->category = $validator->validated()['category'];
        // For nullable fields, use input() or validated() which correctly handles nulls
        $product->description = $validator->validated()['description'] ?? null;


        // --- 6. Save Product and Return Response ---
        if ($product->save()) {
            return response()->json([
                'success' => 'Product updated successfully!',
                'product' => $product, // Changed 'Product' to 'product' for conventional casing
            ], 200); // 200 OK for successful updates
        } else {
            return response()->json([
                'error' => 'Error: Failed to update the product!',
            ], 500); // 500 Internal Server Error for database save failures
        }
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
