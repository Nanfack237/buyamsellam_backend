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
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class EmployeeController extends Controller
{
    public function list(Request $request){

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $employees = Employee::where('store_id', $store_id)->get();

        if($employees){

            $status = 200;
            $response = [
                'success' => 'employees',
                'employees' => $employees,
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

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|min:8',
            'role' => 'required|string',
            'address'=>'required|string',
            'contact'=>'required|integer',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $path = null;

        if($request->hasFile('image')){
            $path = $request->file('image')->store('employeeImages', 'public');
        }

        $storeEmployeeData = $validator->validated();
        
        $employee = Employee::create([

            'name' => $storeEmployeeData['name'],
            'email' => $storeEmployeeData['email'],
            'address' => $storeEmployeeData['address'],
            'contact' => $storeEmployeeData['contact'],
            'image'=> $path,
            'role' => $storeEmployeeData['role'],
            'store_id'=> $store_id,
            'user_id' => 0,
            'status' => 1

        ]);

        if($employee){

            $user_check = User::where('name', $storeEmployeeData['name'])->where('email', $storeEmployeeData['email'])->first();

            if(!$user_check)
            {
                
                $user = User::create([
                    'email' => $storeEmployeeData['email'],
                    'name' => $storeEmployeeData['name'],
                    'image'=> $path,
                    'password' => Hash::make($storeEmployeeData['password']),
                    'role' => $storeEmployeeData['role'],
                    'store_limit' => 1,
                    'status' => 1

                ]);

                $latest_user = User::where('name', $storeEmployeeData['name'])->where('email', $storeEmployeeData['email'])->orderBy('id', 'desc')->value('id');
                $latest_employee = Employee::where('name', $storeEmployeeData['name'])->where('email', $storeEmployeeData['email'])->where('store_id', $store_id)->orderBy('id', 'desc')->first();
            
                if($latest_employee && $latest_user)
                {
                    
                    $latest_employee->user_id = $latest_user;
                    $latest_employee->save();
                }

                $response = [
                    'success' => 'Employee created successfully !',
                    'employee' => $employee,
                ];
        
            } else {

                $response = [
                    'error' => 'error, failed to create the Employee!',
                ];
            }
        }

        return response()->json($response);

    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
            'email' => 'required|string|email|min:4',
            'address' => 'required|string',
            'contact' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $employee = Employee::where('store_id', $store_id)->find($id);

        $editEmployeeData = $validator->validated(); // Get validated data as array

        $employee->name = $editEmployeeData['name'];
        $employee->email = $editEmployeeData['email'];
        $employee->address = $editEmployeeData['address'];
        $employee->contact = $editEmployeeData['contact'];

        if ($employee->save()) {
            $status = 201;
            $response = [
                'error' => 'Employee edited successfully!',
                'employee' => $employee,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to edit the Employee!',
            ];
        }

        return response()->json($response, $status);
    }

    public function show(Request $request, $id)
    {
        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $employee = Employee::where('store_id', $store_id)->find($id);

        if($employee){
            $status = 200;
            $response = [
                'success' => 'Employee',
                'employee' => $employee,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find Employee',
            ];
        }


        return response()->json($response, $status);

    }

    public function showStore(Request $request)
    {
        
        
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $employee = Employee::where('user_id', $user_id)->get();

        if($employee){
            $status = 200;
            $response = [
                'success' => 'Employee',
                'employee' => $employee,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'error, failed to find Employee',
            ];
        }


        return response()->json($response, $status);

    }
}
