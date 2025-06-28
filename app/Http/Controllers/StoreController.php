<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class StoreController extends Controller
{
    public function list(Request $request){

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $stores = Store::where('user_id', $user_id)->get();

        if($stores){

            $status = 200;
            $response = [
                'success' => 'Stores',
                'stores' => $stores,
            ];
            
        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list stores',
            ];

        }

        return response()->json($response, $status);
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'category'=>'required|string|max:30',
            'description'=>'required|string|min:10',
            'location'=>'required|string',
            'contact'=>'required|integer',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        if($request->hasFile('image')){
            $path = $request->file('image')->store('storeImages', 'public');
        }

        $closing_time = '19:00';

        $saveStoreData = $validator->validated();
        $store = Store::create([
            'name' => $saveStoreData['name'],
            'category' => $saveStoreData['category'],
            'description' => $saveStoreData['description'],
            'location'=> $saveStoreData['location'],
            'contact'=> $saveStoreData['contact'],
            'image'=>$path,
            'closing_time'=>$closing_time,
            'daily_summary'=> 1,
            'user_id'=> $user_id,
            'status' => 1
        ]);
        
        if ($store) {

            $status = 201;
            $response = [
                'success' => 'Store created successfully !',
                'store' => $store,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to create a store!',
            ];
        }

        return response()->json($response, $status);

    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|name|unique:stores',
            'category'=>'required|string|max:30',
            'description'=>'required|string|max:255',
            'location'=>'required|string',
            'contact'=>'required|integer',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'closing_time'=>'required|time',
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $store = Store::where('user_id', $userId)->find($id);

        $editStoreData = $validator->validated(); 

        $store->name = $editStoreData['name'];
        $store->category = $editStoreData['category'];
        $store->description = $editStoreData['description'];
        $store->location = $editStoreData['location'];
        $store->contact = $editStoreData['contact'];
        $store->image = $editStoreData['image'];
        $store->closing_time = $editStoreData['closing_time'];

        if ($store->save()) {
            $status = 201;
            $response = [
                'success' => 'Store edited successfully!',
                'store' => $store,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to edit the store!',
            ];
        }

        return response()->json($response, $status);
    }

    public function editStatus(Request $request, $id)
    {

        
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $validator = Validator::make($request->all(), [
            
            'status'=>'required|integer|in:0,1',
            
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $store = Store::where('id', $id)->where('user_id', $user_id)->first();

        $editStoreStatus= $validator->validated(); 

        
        $store->status = $editStoreStatus['status'];
       
        if ($store->save()) {
            $status = 201;
            $response = [
                'success' => 'Status changed successfully!',
                'store' => $store,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to change store status',
            ];
        }

        return response()->json($response, $status);
    }
    public function show(Request $request)
    {
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $store_id = $request->header('X-Store-ID');

        $store = Store::where('user_id', $user_id)->find($store_id);

        if($store){
            $status = 200;
            $response = [
                'success' => 'store',
                'stores' => $store,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to find the store',
            ];
        }

        return response()->json($response, $status);

    }

    public function delete(Request $request, $id)
    {
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $store = Store::where('user_id', $user_id)->find($id);


        if($store->delete()){
            $status = 200;
            $response = [
                'success' => 'Store deleted successfully',
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to delete store',
            ];
        }

        return response()->json($response, $status);
    }



    // 

     public function showStore(Request $request, $id)
    {

        $store = Store::find($id);

        if($store){
            $status = 200;
            $response = [
                'success' => 'store',
                'store' => $store,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to find the store',
            ];
        }

        return response()->json($response, $status);

    }
}
