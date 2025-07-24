<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureStoreAccess;

Route::group(['prefix' => 'auth'], function () {

   
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/checkpassword',[AuthController::class, 'checkPassword']);

    //admin register 

    Route::post('/admin/register', [AuthController::class, 'adminRegister']);
    

});


Route::middleware(Authenticate::class)->group(function () {

    // admin endpoints

    Route::post('/auth/register', [AuthController::class, 'registerUser']);

    Route::get('/admin/userlist', [AuthController::class, 'userList']);
    Route::get('/admin/activeusers', [AuthController::class, 'activeUsers']);
    Route::get('/admin/topusers', [AuthController::class, 'getTopUsers']);
    Route::put('/admin/user/edit/{id}', [AuthController::class, 'editUser']);
    Route::get('/admin/userchartperweek', [AuthController::class, 'userCreatedWeek']);
    Route::get('/admin/activeuserchartperweek', [AuthController::class, 'getWeeklyActiveUsers']);


    Route::get('/admin/storelist', [StoreController::class, 'storeList']);
    Route::get('/admin/activestores', [StoreController::class, 'activeStores']);
    Route::get('/admin/topstores', [StoreController::class, 'getTopStores']);
    Route::get('/admin/storeperweek', [StoreController::class, 'storePerWeek']);


    Route::get('/admin/saleperweek', [SaleController::class, 'adminSalePerWeek']);

    Route::get('/admin/storechartperweek', [StoreController::class, 'storeCreatedWeek']);



    // authentication
    Route::get('/me',[AuthController::class, 'me'])->name('me');
    Route::put('/change-password',[AuthController::class, 'changePassword'])->name('changePassword');
    Route::get('/users',[AuthController::class, 'list'])->name('list');
    
    Route::get('/sendtoken',[AuthController::class, 'sendToken'])->name('sendtoken');
    Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

    Route::get('/user/{id}',[AuthController::class, 'show']);

    Route::get('stores/',[StoreController::class, 'list'])->name('stores.list');
    Route::post('stores/create-store',[StoreController::class, 'store'])->name('stores.store');
    Route::put('stores/edit/{id}/status',[StoreController::class, 'editStatus'])->name('stores.editStatus');
    Route::put('stores/edit/{id}/dailysummary',[StoreController::class, 'editDailySummary'])->name('stores.editDailySummary');

    Route::get('/employees/showstore',[EmployeeController::class, 'showStore'])->name('employees.showStore');

    Route::get('/stores/showstore/{id}',[StoreController::class, 'showStore'])->name('stores.showStore');

    // Cashier endpoints
    Route::post('/sales/cashiersale',[SaleController::class, 'cashierSale'])->name('sales.cashierSale');
    Route::get('/sales/cashierlist',[SaleController::class, 'cashierList'])->name('sales.cashierList');

    // Stock Controller endpoints
    Route::get('/purchases/stockcontrollerpurchase',[PurchaseController::class, 'stockControllerPurchase']);

   
    Route::get('/customers/cashiercustomers/{id}',[CustomerController::class, 'cashierCustomer'])->name('customers.cashierCustomer');
    Route::post('/sales/send-receipt-email',[SaleController::class, 'sendReceiptEmail']);



    Route::middleware(EnsureStoreAccess::class)->group(function () {

        Route::group(['prefix' => 'stores'], function () {

            Route::put('/edit/{id}',[StoreController::class, 'edit'])->name('stores.edit');
            Route::put('/edit/{id}/logo',[StoreController::class, 'editLogo'])->name('stores.editLogo');
            Route::get('/show',[StoreController::class, 'show'])->name('stores.show');
            Route::delete('/delete/{id}',[StoreController::class, 'delete'])->name('stores.delete');
    
        });
        Route::group(['prefix' => 'products'], function () {

            Route::get('/',[ProductController::class, 'list'])->name('products.list');
            Route::post('/store',[ProductController::class, 'store'])->name('products.store');
            Route::put('/edit/{id}',[ProductController::class, 'edit'])->name('products.edit');
            Route::get('/show/{id}',[ProductController::class, 'show'])->name('products.show');
            Route::delete('/delete/{id}',[ProductController::class, 'delete'])->name('products.delete');

        });

        Route::group(['prefix' => 'purchases'], function () {

            Route::get('/',[PurchaseController::class, 'list'])->name('purchases.list');
            Route::get('/totalpurchaseperday',[PurchaseController::class, 'totalPurchasePerDay'])->name('purchases.totalPurchasePerDay');
            Route::get('/purchasesummary',[PurchaseController::class, 'purchaseWeek'])->name('purchases.purchaseWeek');
            Route::post('/store',[PurchaseController::class, 'store'])->name('purchases.store');
            Route::put('/edit/{id}',[PurchaseController::class, 'edit'])->name('purchases.edit');
            Route::get('/show/{id}',[PurchaseController::class, 'show'])->name('purchases.show');
            Route::post('purchasesummaryperperiod', [PurchaseController::class, 'purchaseSummaryPerPeriod']);


            Route::post('totalpurchasecountperperiod', [PurchaseController::class, 'totalPurchaseCountPerPeriod']);
            // Route::delete('/delete/{id}',[PurchaseController::class, 'delete'])->name('purchases.delete');

            // No filter

            Route::get('/totalpurchasecountall',[PurchaseController::class, 'totalPurchaseCountAll']);

        });

        // sales

        Route::group(['prefix' => 'sales'], function () {

            Route::get('/',[SaleController::class, 'list'])->name('sales.list');
            Route::get('/totalsaleperday',[SaleController::class, 'totalSalePerDay'])->name('sales.totalSalePerDay');
            Route::get('/totalprofitweek',[SaleController::class, 'profitWeek'])->name('sales.profitWeek');
            Route::get('/salesummary',[SaleController::class, 'saleWeek'])->name('purchases.saleWeek');
            Route::post('/store',[SaleController::class, 'store'])->name('sales.store');
            Route::get('/show/{id}',[SaleController::class, 'show'])->name('sales.show');
            Route::put('/edit/{id}',[SaleController::class, 'edit'])->name('sales.edit');
            Route::get('/mostsoldproduct',[SaleController::class, 'mostSoldProduct'])->name('sales.mostSoldProduct');
            Route::get('/mostsoldproductofweek',[SaleController::class, 'mostSoldProductOfTheWeek'])->name('sales.mostSoldProductOfTheWeek');
            Route::get('/topcustomer',[SaleController::class, 'topCustomer'])->name('sales.topCustomer');
            Route::get('/topcustomerofweek',[SaleController::class, 'topCustomerOfTheWeek'])->name('sales.topCustomer');
            Route::get('/totalprofitperday', [SaleController::class, 'totalProfitPerDay'])->name('sales.totalProfitPerDay');

            Route::get('/getavailableyears', [SaleController::class, 'getAvailableYears']);

            Route::post('/totalprofitperperiod', [SaleController::class, 'totalProfitPerPeriod']);
            Route::post('/totalsaleperperiod', [SaleController::class, 'totalSalePerPeriod']);
            Route::post('/totalsalecountperperiod', [SaleController::class, 'totalSaleCountPerPeriod']);
            Route::post('/totalprofitperperiod', [SaleController::class, 'totalProfitPerPeriod']);
            Route::post('/salesummaryperperiod', [SaleController::class, 'salesSummaryPerPeriod']);
            Route::post('/profitsummaryperperiod', [SaleController::class, 'profitSummaryPerPeriod']);
            Route::post('/mostsoldproductperperiod', [SaleController::class, 'mostSoldProductPeriod']);
            Route::post('/topcustomerperperiod', [SaleController::class, 'topCustomerPeriod']);
            // Route::delete('/delete',[SaleController::class, 'delete'])->name('sales.delete');

            // No filter

            Route::get('/totalsalecountall',[SaleController::class, 'totalSaleCountAll']);
            Route::get('/totalprofitall',[SaleController::class, 'totalProfitAll']);

        });

        // stock
        Route::group(['prefix' => 'stocks'], function () {

            Route::get('/',[StockController::class, 'list'])->name('stocks.list');
            Route::get('/shortage',[StockController::class, 'shortageCount'])->name('stocks.shortageCount');
            Route::get('/totalStock',[StockController::class, 'totalStock'])->name('stocks.totalStock');
            Route::post('/totalstockperperiod',[StockController::class, 'totalStockPerPeriod'])->name('stocks.totalStockPerPeriod');
            Route::post('/show/{id}',[StockController::class, 'show'])->name('stocks.show');
            Route::post('/store',[StockController::class, 'store'])->name('stocks.store');
            Route::post('/edit/{id}',[StockController::class, 'edit'])->name('stocks.edit');
            // Route::post('/show/{id}',[StockController::class, 'show'])->name('stocks.show');

            Route::put('/updatestockthreshold', [StockController::class, 'updateGlobalStockThreshold']);

        });

        // Supplier
        Route::group(['prefix' => 'suppliers'], function () {

            Route::get('/',[SupplierController::class, 'list'])->name('suppliers.list');
            Route::post('/store',[SupplierController::class, 'store'])->name('suplliers.store');
            Route::put('/edit/{id}',[SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::get('/show/{id}',[SupplierController::class, 'show'])->name('suppliers.show');
            Route::delete('/delete/{id}',[SupplierController::class, 'delete'])->name('suppliers.delete');
          
          
        });

        Route::group(['prefix' => 'customers'], function () {

            Route::get('/',[CustomerController::class, 'list'])->name('customers.list');
            Route::post('/store',[CustomerController::class, 'store'])->name('customers.store');
            Route::put('/edit/{id}',[CustomerController::class, 'edit'])->name('customers.edit');
            Route::get('/show/{id}',[CustomerController::class, 'show'])->name('customers.show');
        });

        
        Route::group(['prefix' => 'employees'], function () {

            Route::get('/',[EmployeeController::class, 'list'])->name('employees.list');
            Route::post('/store',[EmployeeController::class, 'store'])->name('employees.store');
            
            Route::put('/edit/{id}',[EmployeeController::class, 'edit'])->name('employees.edit');
            Route::get('/show/{id}',[EmployeeController::class, 'show'])->name('employees.show');
        });
    });
});


