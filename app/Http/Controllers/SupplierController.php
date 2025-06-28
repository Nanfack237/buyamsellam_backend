<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $suppliers = Supplier::where('store_id', $store_id)->get();

        if($suppliers){

            $status = 200;
            $response = [
                'success' => 'suppliers',
                'suppliers' => $suppliers,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list Suppliers',
            ];

        }

        return response()->json($response, $status);
    }

    public function store(Request $request){

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'address'=>'required|string',
            'contact'=>'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeSupplierData = $validator->validated();
        $supplier = Supplier::create([
            'name' => $storeSupplierData['name'],
            'address' => $storeSupplierData['address'],
            'contact' => $storeSupplierData['contact'],
            'store_id'=> $store_id,
            'user_id'=> $user_id,
            'status' => 1
        ]);

        if ($supplier) {

            $status = 201;
            $response = [
                'success' => 'Supplier stored successfully !',
                'supplier' => $supplier,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to store the Supplier!',
            ];
        }

        return response()->json($response, $status);

    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
            'address' => 'required|string',
            'contact' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $supplier = Supplier::where('store_id', $store_id)->find($id);

        $editSupplierData = $validator->validated(); // Get validated data as array

        $supplier->name = $editSupplierData['name'];
        $supplier->address = $editSupplierData['address'];
        $supplier->contact = $editSupplierData['contact'];

        if ($supplier->save()) {
            $status = 201;
            $response = [
                'error' => 'Supplier edited successfully!',
                'supplier' => $supplier,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to edit the Supplier!',
            ];
        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $supplier = Supplier::where('store_id', $store_id)->find($id);

        if($supplier){
            $status = 200;
            $response = [
                'success' => 'Supplier',
                'supplier' => $supplier,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find supplier',
            ];
        }


        return response()->json($response, $status);

    }

    // public function delete(Request $request, $id)
    // {

    //     $storeData = json_decode($request->store, true);
    //     $store_id = $storeData['id'];

    //     $supplier = Supplier::where('store_id', $store_id)->find($id);
    //     if($supplier->delete()){
    //         $status = 200;
    //         $response = [
    //             'success' => 'Supplier deleted successfully',
    //         ];
    //     } else {
    //         $status = 422;
    //         $response = [
    //             'error' => 'error, failed to delete supplier',
    //         ];
    //     }

    //     return response()->json($response, $status);
    // }
}
