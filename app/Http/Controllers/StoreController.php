<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; 

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

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $user = User::find($user_id);
        $userEmail = $user->email;

        $validator = Validator::make($request->all(), [
            'name'=>'required|string|min:4',
            'category'=>'required|string|max:30',
            'description'=>'required|string|min:10',
            'location'=>'required|string',
            'contact'=>'required|integer',
            'image1'=>'required|image|mimes:jpeg,png,jpg,gif',
            'image2'=>'required|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $path1 = null;
        $path2 = null;

        if($request->hasFile('image1') && $request->hasFile('image2')){
            $path1 = $request->file('image1')->store("stores/store_$userEmail/storeImages", 'public');
            $path2 = $request->file('image2')->store("stores/store_$userEmail/storeImages", 'public');
        }

        $closing_time = '19:00';

        $saveStoreData = $validator->validated();
        $store = Store::create([
            'name' => $saveStoreData['name'],
            'category' => $saveStoreData['category'],
            'description' => $saveStoreData['description'],
            'location'=> $saveStoreData['location'],
            'contact'=> $saveStoreData['contact'],
            'image1'=>$path1,
            'image2'=>$path2,
            'logo'=> null,
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
        // Decode user data from the request to get the user ID
        $userData = json_decode($request->user, true);

        // Basic validation for user data from the request, if it's crucial for identifying the user.
        // It's generally better to get the authenticated user directly using Auth::user()
        // or through route model binding if the route is protected by 'auth:api' middleware.
        if (!isset($userData['id'])) {
            return response()->json(['error' => 'User ID is missing from the request.'], 400);
        }
        $userId = $userData['id'];

        // Find the user to get their email for directory naming
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userEmail = $user->email;

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:3',
            'category'    => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'location'    => 'required|string',
            'contact'     => 'required|digits_between:8,15', // 'integer' might be too broad; 'digits' or 'numeric' are better for phone numbers
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Changed to nullable
            'image1'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Added validation for image1
            'image2'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Added validation for image2
            'closing_time' => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Find the store associated with the authenticated user and the given ID
        $store = Store::where('user_id', $userId)->find($id);

        if (!$store) {
            return response()->json(['error' => 'Store not found or unauthorized.'], 404);
        }

        // Get validated data
        $editStoreData = $validator->validated();

        // Handle file uploads (logo, image1, image2)
        // Only update if a new file is provided; otherwise, keep existing.
        // If you want to allow clearing files, you'd need additional logic (e.g., specific 'clear_logo' field in request).

        // Handle Logo
        if ($request->hasFile('logo')) {
            // Delete old logo if it exists
            if ($store->logo) {
                Storage::disk('public')->delete($store->logo);
            }
            $store->logo = $request->file('logo')->store("stores/store_$userEmail/storeLogos", 'public');
        } elseif ($request->input('logo') === 'CLEAR_IMAGE') {
            if ($store->logo) {
                Storage::disk('public')->delete($store->logo);
            }
            $store->logo = null;
        }

        // Handle Image 1
        if ($request->hasFile('image1')) {
            // Delete old image1 if it exists
            if ($store->image1) {
                Storage::disk('public')->delete($store->image1);
            }
            $store->image1 = $request->file('image1')->store("stores/store_$userEmail/storeImages", 'public');
        } elseif ($request->input('image1') === 'CLEAR_IMAGE') {
            if ($store->image1) {
                Storage::disk('public')->delete($store->image1);
            }
            $store->image1 = null;
        }

        // Handle Image 2
        if ($request->hasFile('image2')) {
            // Delete old image2 if it exists
            if ($store->image2) {
                Storage::disk('public')->delete($store->image2);
            }
            $store->image2 = $request->file('image2')->store("stores/store_$userEmail/storeImages", 'public');
        } elseif ($request->input('image2') === 'CLEAR_IMAGE') {
            if ($store->image2) {
                Storage::disk('public')->delete($store->image2);
            }
            $store->image2 = null;
        }

        // Update store attributes with validated data
        $store->name = $editStoreData['name'];
        $store->category = $editStoreData['category'];
        $store->description = $editStoreData['description'];
        $store->location = $editStoreData['location'];
        $store->contact = $editStoreData['contact'];
        $store->closing_time = $editStoreData['closing_time'];

        // Save the updated store
        if ($store->save()) {
            return response()->json([
                'success' => 'Store updated successfully!',
                'store' => $store,
            ], 200); // Changed to 200 OK for successful updates
        } else {
            return response()->json([
                'error' => 'Error: Failed to update the store.',
            ], 500); // Internal Server Error for save failures
        }
    }
    
    public function editLogo(Request $request, $id)
    {
        // Decode user data from the request to get the user ID
        // Assuming 'user' is a JSON string passed in the request body
        $userData = json_decode($request->user, true);
        $userId = $userData['id'];

        // Find the user to get their email for directory naming
        $user = User::find($userId);

        // Check if the user exists
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $userEmail = $user->email;

        // Validate the incoming request data for the logo
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Max 2MB
        ]);

        // If validation fails, return the errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Find the store associated with the authenticated user and the given ID
        $store = Store::where('user_id', $userId)->find($id);

        // Check if the store exists for this user
        if (!$store) {
            return response()->json(['error' => 'Store not found or unauthorized.'], 404);
        }

        // Handle logo upload or clear request
        if ($request->hasFile('logo')) {
            // New image uploaded
            // Delete the old logo if it exists
            if ($store->logo) {
                Storage::disk('public')->delete($store->logo);
            }

            // Store the new logo in a dedicated directory for the user's stores
            // Using double quotes for string interpolation with backticks
            $logoPath = $request->file('logo')->store("stores/store_$userEmail/storeLogos", 'public');

            // Update the store's logo path in the database
            $store->logo = $logoPath;

        } elseif ($request->input('logo') === 'CLEAR_IMAGE') {
            // Frontend sent 'CLEAR_IMAGE' to signal clearing the image
            // Delete the existing logo if it exists
            if ($store->logo) {
                Storage::disk('public')->delete($store->logo);
            }
            // Set the database field to null
            $store->logo = null;
        } else {
            // If neither a file is uploaded nor a 'CLEAR_IMAGE' signal is sent,
            // but a 'logo' field is present and invalid, then the validation should catch it.
            // If no 'logo' field at all, the 'required' rule would fail.
            // This 'else' block might indicate an edge case or missing input which
            // the validator might already handle, but it's good to consider.
            return response()->json(['error' => 'Invalid logo operation.'], 400);
        }

        // Save the changes to the store model
        if ($store->save()) {
            return response()->json([
                'success' => 'Store logo updated successfully!',
                'store' => $store,
            ], 200); // Changed status to 200 OK for updates
        } else {
            return response()->json([
                'error' => 'Error: Failed to update store logo.',
            ], 500); // Changed status to 500 Internal Server Error for save failures
        }
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
            $status = 200;
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

    public function editDailySummary(Request $request, $id)
    {

        
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $validator = Validator::make($request->all(), [
            
            'daily_summary'=>'required|integer|in:0,1',
            
        ]);

        if ($validator->fails()) {
            return $this->error('error', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $store = Store::where('user_id', $user_id)->find($id);

        $editStoreDaily = $validator->validated(); 

        
        $store->daily_summary = $editStoreDaily['daily_summary'];
       
        if ($store->save()) {
            $status = 200;
            $response = [
                'success' => 'Daily Summary changed successfully!',
                'store' => $store,
            ];
        } else {
            $status = 422;
            $response = [
                'error' => 'Error, failed to change store Daily Summary',
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


    // admin functions

    public function storeList(Request $request){

        $userData = json_decode($request->user, true);
        $userId = $userData['id'];

        $userCheck = User::find($userId);
        $adminRole = "admin";
       
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if(!$userData['role'] === $adminRole){
            return response()->json(['error' => 'You are not authorized to do this operation'], 401);
        } else {
            $stores = Store::all();

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
    }

    public function activeStores(Request $request)
    {

        $userData = json_decode($request->user, true);
        $userId = $userData['id'];

        $userCheck = User::find($userId);
        $adminRole = "admin";
       
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if(!$userData['role'] === $adminRole){
            return response()->json(['error' => 'You are not authorized to do this operation'], 401);
        } else {
            $stores = Store::where('status', 1)->get();

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
    }

    public function getTopStores(Request $request)
    {
        // 1. User Authentication & Authorization Check (as before)
        $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }

        $userId = $userData['id'];
        $userRole = $userData['role']; // Get role from decoded user data

        // Check if the user exists in the database
        $userCheck = User::find($userId);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Authorization: Check if the user has the 'admin' role
        $adminRole = "admin"; // Define role string once
        if ($userRole !== $adminRole) { // Use strict comparison !==
            return response()->json(['error' => 'You are not authorized to do this operation'], 401);       
        }

        // 2. Query for top selling stores
        // Join stores and sales tables
        // Count sales for each store
        // Group by store details and order by sales count
        $topStores = Store::select(
            'stores.id',
            'stores.name', // Assuming 'name' is the store name column
            // You can add other store columns here if needed
            \DB::raw('COUNT(sales.id) as total_sales') // Count sales for each store
        )
            ->leftJoin('sales', 'stores.id', '=', 'sales.store_id') // Use leftJoin to include stores with 0 sales
            ->groupBy('stores.id', 'stores.name') // Group by all selected store columns
            ->orderByDesc('total_sales') // Order by total sales descending
            ->limit(5) // Limit to the top N stores (default to 1)
            ->get();

        // 3. Handle response
        if ($topStores) {
            $message = ($topStores === 1)
                ? 'The top store get successfully.'
                : 'Top Stores get Successfully';

            return response()->json([
                'success' => $message,
                'topstores' => $topStores, // This will be a collection of store objects with 'total_sales'
            ], 200); // 200
        } else {
            return response()->json([
                'message' => 'No Store with sale.',
                'topstores' => [], // Return an empty array if no stores/sales found
            ], 200); // Or 200 if you consider an empty list a successful result
        }
    }

    public function storePerWeek(Request $request)
    {
        // 1. User Authentication & Authorization Check
        // It's generally better to use Laravel's built-in authentication and authorization
        // features (like Auth::user() and policies/gates) rather than passing user data in the request body.
        // However, adapting to your current structure for now:

        $userData = json_decode($request->user, true); // Assuming 'user' is a JSON string in the request body

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400); // Bad Request
        }

        $userId = $userData['id'];
        $userRole = $userData['role']; // Get role from decoded user data

        // Check if the user exists in the database
        $userCheck = User::find($userId);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Authorization: Check if the user has the 'admin' role
        $adminRole = "admin"; // Define role string once
        if ($userRole !== $adminRole) { // Use strict comparison !==
            return response()->json(['error' => 'You are not authorized to do this operation'], 401);       
        }

        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0); // Start of Monday (00:00:00)
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY)->setTime(23, 59, 59); // End of Sunday (23:59:59)

       
        $stores = Store::whereBetween('created_at', [$startDate, $endDate])->get();

        // 4. Response Handling
        // Always return a JSON response with appropriate status codes.

        if ($stores->isNotEmpty()) { // Check if the collection is not empty
            return response()->json([
                'success' => 'Stores retrieved successfully.', // More descriptive success message
                'stores' => $stores,
               
            ], 200); // OK
        } else {
            // If no stores are found for the week, it's not an "error, failed to list stores"
            // it's just that there are no stores. A 200 OK with an empty array or a specific message is better.
            return response()->json([
                'message' => 'No stores found for the current week.',
                'stores' => [], // Return an empty array
            ], 200); // Still 200 OK if the operation was successful but yielded no results
        }
    }

    public function storeCreatedWeek(Request $request)
    {
        // 1. User Authentication & Authorization Check (as before)
        $userData = json_decode($request->user, true);

        if (!isset($userData['id']) || !isset($userData['role'])) {
            return response()->json(['error' => 'Invalid user data provided.'], 400);
        }

        $userId = $userData['id'];
        $userRole = $userData['role'];

        $userCheck = User::find($userId);
        if (!$userCheck) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $adminRole = "admin";
        if ($userRole !== $adminRole) {
            return response()->json(['error' => 'You are not authorized to do this operation'], 403);
        }

        // 2. Define the current week's date range (as before)
        $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0);
        $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY)->setTime(23, 59, 59);

        // 3. Query the Store model to count creations per day (as before)
        $storesCount = Store::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DAYOFWEEK(created_at) as weekday, COUNT(id) as count')
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get();

        // 4. Initialize weekly counts with zeros for all days (as before)
        // DAYOFWEEK returns 1 for Sunday, 2 for Monday, ..., 7 for Saturday.
        $weeklyTotals = array_fill(1, 7, 0);

        foreach ($storesCount as $dailyCount) {
            $weekday = (int) $dailyCount->weekday;
            $weeklyTotals[$weekday] = (int) $dailyCount->count;
        }

        // 5. Determine labels based on requested locale
        $locale = $request->query('locale', 'en'); // Get locale from query parameter, default to 'en'

        $dayLabels = [];
        if ($locale === 'fr') {
            // Full 7 days in French
            $dayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        } else { // Default to English or any other locale not explicitly 'fr'
            // Full 7 days in English
            $dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        }

        // 6. Reorder the data from DAYOFWEEK (1=Sunday, 2=Monday...) to Monday-Sunday (as before)
        $orderedData = [];
        $orderedData[] = $weeklyTotals[2]; // Monday
        $orderedData[] = $weeklyTotals[3]; // Tuesday
        $orderedData[] = $weeklyTotals[4]; // Wednesday
        $orderedData[] = $weeklyTotals[5]; // Thursday
        $orderedData[] = $weeklyTotals[6]; // Friday
        $orderedData[] = $weeklyTotals[7]; // Saturday
        $orderedData[] = $weeklyTotals[1]; // Sunday

        // 7. Prepare and return the JSON response (as before)
        return response()->json([
            'success' => true,
            'labels' => $dayLabels, // Now determined by the backend based on locale
            'data' => $orderedData,
          
        ], 200);
    }
}
