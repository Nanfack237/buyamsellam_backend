<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $customers = Customer::where('store_id', $store_id)->get();

        if($customers){

            $status = 200;
            $response = [
                'success' => 'customers',
                'customers' => $customers,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list customers',
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
            'contact'=>'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $storeCustomerData = $validator->validated();
        $customer = Customer::create([
            'name' => $storeCustomerData['name'],
            'contact' => $storeCustomerData['contact'],
            'store_id'=> $store_id,
            'user_id'=> $user_id,
            'status' => 1
        ]);

        if ($customer) {

            $status = 201;
            $response = [
                'success' => 'Customer stored successfully !',
                'customer' => $customer,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to store the Customer!',
            ];
        }

        return response()->json($response, $status);

    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
            'contact' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $userData = json_decode($request->store, true);
        $user_id = $userData['id'];

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $customer = Customer::where('store_id', $store_id)->find($id);

        $editCustomerData = $validator->validated(); // Get validated data as array

        $customer->name = $editCustomerData['name'];
        $customer->contact = $editCustomerData['contact'];

        if ($customer->save()) {
            $status = 201;
            $response = [
                'error' => 'Customer edited successfully!',
                'customer' => $customer,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to edit the Customer!',
            ];
        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $customer = Customer::where('store_id', $store_id)->find($id);

        if($customer){
            $status = 200;
            $response = [
                'success' => 'Customer',
                'customer' => $customer,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find customer',
            ];
        }


        return response()->json($response, $status);

    }


    // Cashier Customer

    public function cashierCustomer(Request $request, $id){

       
        $customers = Customer::where('store_id', $id)->get();

        if($customers){

            $status = 200;
            $response = [
                'success' => 'customers',
                'customers' => $customers,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'error, failed to list customers',
            ];

        }

        return response()->json($response, $status);
    }
}
