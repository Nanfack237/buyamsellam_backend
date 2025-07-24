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
use Illuminate\Support\Facades\Storage; 


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
        

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $user = User::find($user_id);
        $userEmail = $user->email;

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|min:8',
            'role' => 'required|string',
            'address'=>'required|string',
            'contact'=>'required|integer',
            'image'=>'required|image|mimes:jpeg,png,jpg,gif|max:32768',
           
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $path = null;

        if($request->hasFile('image')){
            $path = $request->file('image')->store("stores/store_$userEmail/employeeImages", 'public');
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

        $userData = json_decode($request->user, true);
        $userId = $userData['id'];

        // Find the user to get their email for directory naming
        $user = User::find($userId);

        // Check if the user exists
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userEmail = $user->email;

        $storeData = json_decode($request->store, true);
        $store_id = $storeData['id'];

        // 2. Find the employee by store_id and ID
        $employee = Employee::where('store_id', $store_id)->find($id);

        // 3. If employee not found, return 404
        if (!$employee) {
            return response()->json(['error' => 'Employee not found.'], Response::HTTP_NOT_FOUND); // 404 Not Found
        }

        // 4. Define validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4|max:255', // Added max:255
            'email' => 'required|string|email|min:4|max:255', // Added max:255
            'address' => 'required|string|max:255', // Added max:255
            'contact' => ['required', 'string', 'regex:/^\+?\d{6,15}$/'], // Changed to string and added regex for phone numbers
            'role' => 'required|string|in:manager,cashier,stock_controller,staff', // Added validation for role
            'status' => 'required|integer|in:0,1', // Added validation for status (0 or 1)
            'image' => 'nullable|image|mimes:jpeg,png,bmp,gif,webp|max:2048', // Allows images, optional, max 2MB
        ]);

        // 5. If validation fails, return validation errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
        }

        // 6. Handle image upload/removal logic
        if ($request->hasFile('image')) {
            // New image uploaded, delete old one if exists
            if ($employee->image) {
                Storage::disk('public')->delete($employee->image);
            }
            $employee->image = $request->file('image')->store("stores/store_$userEmail/employeeImages", 'public');
        } elseif ($request->input('image') === 'CLEAR_IMAGE') {
            // Frontend sent 'CLEAR_IMAGE', delete existing image
            if ($employee->image) {
                Storage::disk('public')->delete($employee->image);
            }
            $employee->image = null; // Set DB field to null
        }
        // else: If 'image' is not present in the request (neither file nor 'CLEAR_IMAGE'),
        // it means the image was untouched on the frontend. Do nothing,
        // so $employee->image retains its current value.

        // 7. Update other employee fields from validated data
        $employee->name = $request->input('name');
        $employee->email = $request->input('email');
        $employee->address = $request->input('address');
        $employee->contact = $request->input('contact');
        $employee->role = $request->input('role');
        $employee->status = $request->input('status');

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if($user){

            $user->status = $request->input('status');
            if($user->save()){

                if ($employee->save()) {
                    $status = 200;
                    $response = [
                        'success' => 'Employee edited successfully!',
                        'employee' => $employee,
                    ];
                } else {
                    $status = 422;
                    $response = [
                        'error' => 'Error, failed to edit the Employee!',
                    ];
                }
            }


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
