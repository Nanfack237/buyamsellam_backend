<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginLog;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $userData = json_decode($request->user, true);

        if ($userData) {

            $user = User::find($userData['id']);

            if ($user) {

                $status = 200;
                $response = [
                    'success' => 'Your Details',
                    'user' => $user,
                ];

            } else {
                $status = 422;
                $response = [
                    'error' => 'Error, failed to display information',
                ];
            }

            return response()->json($response, $status);

        } else {
            return response()->json('Unauthorized Access', 401);
        }
    }

    public function list(Request $request)
    {

        $users = User::all();

        if ($users) {
            $status = 200;
            $response = [
                'success' => 'Users',
                'users' => $users,
            ];

        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to display information',
            ];
        }

        return response()->json($response, $status);

    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            // Ensure you only return necessary data, not sensitive info like password
            return response()->json(['success' => true, 'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Add other fields relevant to the manager here
            ]]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred.', 'error' => $e->getMessage()], 500);
        }
    }


    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email'=>'required|string|email',
            'password'=>'required|min:8'
        ]);

        $date = now()->toDateString();
        $login_time = now()->toTimeString();

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $loginUserData = $validator->validated();

        $user = User::where('email',$loginUserData['email'])->first();
        if(!$user || !Hash::check($loginUserData['password'],$user->password)){
            $status = 401;
            $response = [
                'error' => 'Invalid credentials'
            ];

            return response()->json($response, $status);
        } else {
        

            if ($user->status === 0) {
            $status = 403; // 403 Forbidden
            $response = [
                'error' => 'Your account has been locked. Please contact your manager or support for assistance.'
            ];
           
            } else {

                LoginLog::create([
                    'user_id' => $user->id,
                    'store_id' => $user->null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'date' => $date,
                    'login_time' => $login_time,
                ]);

                $token = Str::random(80);
                $apiToken = Hash::make($token);

                $user->api_token = $apiToken;
                $user->save();

                $status = 200;
                $response = [
                    'success' => 'User Connected!',
                    'user' => $user,
                    'token' => $apiToken
                ];
            // $header = [
            //     'Authorization' => "Bearer {$apiToken}",
            // ];
            }

            return response()->json($response, $status);
        }

    }

    public function checkPassword(Request $request){
        
        $validator = Validator::make($request->all(), [
            'email'=>'required|string|email',
            'password'=>'required|min:8'
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

         $loginUserData = $validator->validated();

        $user = User::where('email',$loginUserData['email'])->first();
        if(!$user || !Hash::check($loginUserData['password'],$user->password)){
            $status = 200;
            $response = [
                'error' => 'Wrong password'
            ];

            return response()->json($response, $status);
        } else {

            $status = 200;
            $response = [
                'success' => 'Valid password'
            ];

            return response()->json($response, $status);
        }
        
    }

    public function changePassword(Request $request){
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        if (!$user_id){
            $status = 400;
            $response = [
                'error' => 'User Id not found.',
            ];
        }

        $user = User::find($user_id);

        if (!$user){
            $status = 400;
            $response = [
                'error' => 'User not found.',
            ];
        }
        
        $validator = Validator::make($request->all(), [
            'current_password'=>'required|min:8',
            'new_password'=>'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $changeUserData = $validator->validated();

        if(!Hash::check($request->current_password, $user->password)){
            $status = null;
            $response = [
                'error' => 'Current password is incorrect.',
            
            ];
        } else {

        $user->password = bcrypt($request->new_password);
        $user->save();

        $status = 200;
            $response = [
                'success' => 'Password changed successfully.',
            ];

        }

        return response()->json($response, $status);

    }

    public function logout(Request $request){

        $userData = json_decode($request->user, true);
        $token = $request->header('Authorization'); // Check for token in headers

        if (!$token) {

            return response()->json('Unauthorized Access', 401);

        } else {

            $user = User::find($userData['id']);

            $tokenlogout = null;
            $user->api_token = $tokenlogout;
            if($user->save()){
                $status = 200;
                $response = [
                    'success' => 'User logout !',
                ];
            } else {
                $status = 422;
                $response = [
                    'error' => 'Error, failed to log out',
                ];
            }
            return response()->json($response, $status);
        }
    }

            // Admin side

    public function adminRegister(Request $request){
        $validator = Validator::make($request->all(), [
            'email'=>'required|string|email|unique:users',
            'name'=>'required|min:3',
            'password'=>'required|min:8',
            'address'=>'required|string',
            'contact'=>'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }


        $registerUserData = $validator->validated();
        $user = User::create([
            'email' => $registerUserData['email'],
            'name' => $registerUserData['name'],
            'password' => Hash::make($registerUserData['password']),
            'address' => $registerUserData['address'],
            'contact' => $registerUserData['contact'],
            'image' => 'images/admin',
            'role' => 'admin',
            'store_limit' => 100,
            'status' => 1
        ]);

        if($user){

            $status = 201;
            $response = [
                'success' => 'User registered successfully',
                'user' => $user,
            ];

        } else {

            $status = 422;
            $response = [
                'error' => 'Error, failed to create a user',
            ];

        }

        return response()->json($response, $status);

    }


    public function registerUser(Request $request)
    {


        $userData = json_decode($request->user, true);

        $adminRole = "admin";

        if(!$userData['role'] === $adminRole){

            return response()->json(['error' => 'Your not authorized to do this operation'], 401);

        } else {

            $validator = Validator::make($request->all(), [
                'email'=>'required|string|email|unique:users',
                'name'=>'required|min:3',
                'password'=>'required|min:8',
                'address'=>'nullable|string',
                'contact'=>'nullable|integer',
                'image'=>'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

        
            $registerUserData = $validator->validated();

            $userEmail = $registerUserData['email'];

            $path = null;

            if($request->hasFile('image')){
                $path = $request->file('image')->store("users/user_$userEmail/userImages", 'public');
            }

            $user = User::create([
                'email' => $registerUserData['email'],
                'name' => $registerUserData['name'],
                'password' => Hash::make($registerUserData['password']),
                'address'=> $registerUserData['address'],
                'contact'=> $registerUserData['contact'],
                'image'=> $path,
                'role' => 'manager',
                'store_limit' => 1,
                'status' => 1
            ]);

            if($user){

                $status = 201;
                $response = [
                    'success' => 'User registered successfully',
                    'user' => $user,
                ];

            } else {

                $status = 422;
                $response = [
                    'error' => 'Error, failed to create a user',
                ];

            }

            return response()->json($response, $status);
        }

    }


    public function userList(Request $request)
    {

        
        $userData = json_decode($request->user, true);

        $adminRole = "admin";

        if(!$userData['role'] === $adminRole){

            return response()->json(['error' => 'Your not authorized to do this operation'], 401);

        } else {
        
            $checkRole = "manager";
            $users = User::where('role', $checkRole)->get();

            if ($users) {
                $status = 200;
                $response = [
                    'success' => 'Users',
                    'users' => $users,
                ];

            } else {
                $status = 422;
                $response = [
                    'error' => 'Error, failed to display information',
                ];
            }
       
            return response()->json($response, $status);
        }

    }

    public function activeUsers(Request $request)
    {

        
        $userData = json_decode($request->user, true);

        $adminRole = "admin";

        if(!$userData['role'] === $adminRole){

            return response()->json(['error' => 'Your not authorized to do this operation'], 401);

        } else {
        
            $checkRole = "manager";
            $users = User::where('role', $checkRole)->where('status', 1)->get();

            if ($users) {
                $status = 200;
                $response = [
                    'success' => 'Users',
                    'users' => $users,
                ];

            } else {
                $status = 422;
                $response = [
                    'error' => 'Error, failed to display information',
                ];
            }
       
            return response()->json($response, $status);
        }

    }


    public function editUser(Request $request, $id)
    {

        $userData = json_decode($request->user, true);
        $userId = $userData['id'];

        // Find the user to get their email for directory naming
        $userCheck = User::find($userId);
        $adminRole = "admin";
       
        // Check if the user exists
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if(!$userData['role'] === $adminRole){
            return response()->json(['error' => 'Your not authorized to do this operation'], 401);
        } else {

            $checkRole = "manager";

            // 2. Find the employee by store_id and ID
            $user = User::where('role', $checkRole)->find($id);

            // 3. If employee not found, return 404
            if (!$user) {
                return response()->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND); // 404 Not Found
            }

            // 4. Define validation rules
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:4|max:255', // Added max:255
                'email' => 'required|string|email|min:4|max:255', // Added max:255
                'address' => 'required|string|max:255', // Added max:255
                'contact' => ['required', 'string', 'regex:/^\+?\d{6,15}$/'], // Changed to string and added regex for phone numbers
                'role' => 'required|string', // Added validation for role
                'store_limit' => 'required|integer',
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
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $path = $request->file('image')->store("users/user_$userEmail/userImages", 'public');
            } elseif ($request->input('image') === 'CLEAR_IMAGE') {
                // Frontend sent 'CLEAR_IMAGE', delete existing image
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->image = null; // Set DB field to null
            }
            // else: If 'image' is not present in the request (neither file nor 'CLEAR_IMAGE'),
            // it means the image was untouched on the frontend. Do nothing,
            // so $user->image retains its current value.

            // 7. Update other user fields from validated data
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->address = $request->input('address');
            $user->contact = $request->input('contact');
            $user->role = $request->input('role');
            $user->store_limit = $request->input('store_limit');
            $user->status = $request->input('status');

            if($user){

                $user->status = $request->input('status');
                if($user->save()){

                    if ($user->save()) {
                        $status = 200;
                        $response = [
                            'success' => 'User edited successfully!',
                            'user' => $user,
                        ];
                    } else {
                        $status = 422;
                        $response = [
                            'error' => 'Error, failed to edit the User!',
                        ];
                    }
                }


            }

            

            return response()->json($response, $status);
        }
    }



    public function getTopUsers(Request $request)
    {
        // 1. User Authentication & Authorization Check
        // (Assuming $request->user is passed as a JSON string for authorization,
        // but remember to prefer Laravel's built-in Auth::user() and policies/gates)
        $userData = json_decode($request->user, true);

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userId = $userData['id'];
        $userRole = $userData['role'];

        $userCheck = User::find($userId);
        if (!$userCheck) {

            return response()->json(['error' => 'User not found.'],404); // 404 Not Found
            
        }

        $adminRole = "admin";
        if ($userRole !== $adminRole) {
            return response()->json(['error' => 'Your not authorized to do this operation'], 401);        
        }

        $role = "manager";
        // 2. Query for the user with the highest store_limit
        $topUsers = User::orderByDesc('store_limit') // Order by store_limit in descending order
                       ->select('email', 'store_limit') // Select only the required columns
                       ->where('role', $role)
                       ->limit(5)
                       ->get(); // Get the first result (which will be the highest)

        // 3. Handle response
        if ($topUsers) {
            return response()->json([
                'success' => true,
                'topUsers' => $topUsers, // This will contain 'email' and 'store_limit'
            ], 200); // 200
        } else {
            // No users found in the database, or no user has a store_limit set (if it's nullable)
            return response()->json([
                'error' => true,
                'topUsers' => null,
            ], 404); // 404 if the resource isn't found, or 200 with message if it's a valid empty result
                                            // 404 is more appropriate if you expect there always to be one,
                                            // or 200 if it's a 'no results' scenario. Let's go with 404 for 'not found'.
        }
    }

    // admin analysis functions

     public function userCreatedWeek(Request $request)
    {
        // 1. User Authentication & Authorization Check
        // (Keep this as per your existing implementation, assuming $request->user is passed)
        $userData = json_decode($request->user, true);

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userId = $userData['id'];
        $userRole = $userData['role'];

        $userCheck = User::find($userId);
        if (!$userCheck) {

            return response()->json(['error' => 'User not found.'],404); // 404 Not Found
            
        }

        $adminRole = "admin";
        if ($userRole !== $adminRole) {
            return response()->json(['error' => 'Your not authorized to do this operation'], 401);        
        }

        $role = "manager";

        // 2. Define the current week's date range
        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0);
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY)->setTime(23, 59, 59);

        // 3. Query the User model to count creations per day
        $usersCount = User::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DAYOFWEEK(created_at) as weekday, COUNT(id) as count')
            ->where('role', $role)
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get();

        // 4. Initialize weekly counts with zeros for all days
        // DAYOFWEEK returns 1 for Sunday, 2 for Monday, ..., 7 for Saturday.
        $weeklyTotals = array_fill(1, 7, 0);

        foreach ($usersCount as $dailyCount) {
            $weekday = (int) $dailyCount->weekday;
            $weeklyTotals[$weekday] = (int) $dailyCount->count;
        }

        // 5. Determine labels based on requested locale
        $locale = $request->query('locale', 'en'); // Get locale from query parameter, default to 'en'

        $dayLabels = [];
        if ($locale === 'fr') {
            $dayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        } else { // Default to English or any other locale not explicitly 'fr'
            $dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        }

        // 6. Reorder the data from DAYOFWEEK (1=Sunday, 2=Monday...) to Monday-Sunday
        $orderedData = [];
        $orderedData[] = $weeklyTotals[2]; // Monday
        $orderedData[] = $weeklyTotals[3]; // Tuesday
        $orderedData[] = $weeklyTotals[4]; // Wednesday
        $orderedData[] = $weeklyTotals[5]; // Thursday
        $orderedData[] = $weeklyTotals[6]; // Friday
        $orderedData[] = $weeklyTotals[7]; // Saturday
        $orderedData[] = $weeklyTotals[1]; // Sunday

        // 7. Prepare and return the JSON response
        return response()->json([
            'success' => 'Statistiques de création d\'utilisateurs par jour récupérées avec succès.',
            'labels' => $dayLabels, // Now determined by the backend based on locale
            'data' => $orderedData,
            'week_start' => $startDate->toDateString(),
            'week_end' => $endDate->toDateString(),
        ], 200);
    }


    public function getWeeklyActiveUsers(Request $request)
    {
        // 1. User Authentication & Authorization Check
       $userData = json_decode($request->user, true);

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userId = $userData['id'];
        $userRole = $userData['role'];

        $userCheck = User::find($userId);
        if (!$userCheck) {

            return response()->json(['error' => 'User not found.'],404); // 404 Not Found
            
        }

        $adminRole = "admin";
        if ($userRole !== $adminRole) {
            return response()->json(['error' => 'Your not authorized to do this operation'], 401);        
        }

        // 2. Define the current week's date range
        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0);
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY)->setTime(23, 59, 59);

        // 3. Query LoginLog to get unique users per day
        // Select distinct user_id for each 'date' within the week
        $activeUsersPerDay = LoginLog::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]) // 'date' column holds just the date
            ->selectRaw('DATE(date) as login_date, COUNT(DISTINCT user_id) as unique_users_count')
            ->groupBy('login_date')
            ->orderBy('login_date') // Order by the date itself
            ->get();

        // 4. Initialize weekly counts with zeros for all days of the week
        // We need to map dates to weekdays for the chart labels
        $weeklyActiveUsersData = array_fill(1, 7, 0); // 1-7 for DAYOFWEEK (Sunday-Saturday)

        foreach ($activeUsersPerDay as $dailyCount) {
            $carbonDate = Carbon::parse($dailyCount->login_date);
            $weekday = $carbonDate->dayOfWeekIso; // 1 for Monday, 7 for Sunday (ISO 8601 standard)

            $dayOfWeekMySQL = Carbon::parse($dailyCount->login_date)->dayOfWeek + 1; // Carbon's dayOfWeek is 0 (Sunday) - 6 (Saturday). MySQL's DAYOFWEEK is 1 (Sunday) - 7 (Saturday).
            $weeklyActiveUsersData[$dayOfWeekMySQL] = (int) $dailyCount->unique_users_count;
        }

        // 5. Determine labels based on requested locale
        $locale = $request->query('locale', 'en');
        $dayLabels = [];
        if ($locale === 'fr') {
            $dayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        } else {
            $dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        }

        // 6. Reorder data for Monday-Sunday display (matching labels)
        $orderedData = [];
        $orderedData[] = $weeklyActiveUsersData[2]; // Monday (MySQL DAYOFWEEK = 2)
        $orderedData[] = $weeklyActiveUsersData[3]; // Tuesday (MySQL DAYOFWEEK = 3)
        $orderedData[] = $weeklyActiveUsersData[4]; // Wednesday (MySQL DAYOFWEEK = 4)
        $orderedData[] = $weeklyActiveUsersData[5]; // Thursday (MySQL DAYOFWEEK = 5)
        $orderedData[] = $weeklyActiveUsersData[6]; // Friday (MySQL DAYOFWEEK = 6)
        $orderedData[] = $weeklyActiveUsersData[7]; // Saturday (MySQL DAYOFWEEK = 7)
        $orderedData[] = $weeklyActiveUsersData[1]; // Sunday (MySQL DAYOFWEEK = 1)


        // 7. Prepare and return the JSON response
        return response()->json([
            'success' => true,
            'labels' => $dayLabels,
            'data' => $orderedData,
           
        ], 200);
    }
}