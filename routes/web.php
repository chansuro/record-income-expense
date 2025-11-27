<?php
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\admin\LoginController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\CustomerController;
use App\Http\Controllers\admin\TransactionController;
use App\Http\Controllers\admin\MillageController;
use App\Http\Controllers\admin\EmailTemplateController;
use App\Http\Controllers\admin\CategoryListController;
use App\Http\Controllers\admin\EditCategoryController;
use App\Http\Controllers\admin\SubscriptionController;
use App\Http\Controllers\web\signupController;


use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});
Route::get('datasafety', function () {
    return view('datasafty');
});
Route::get('privacy', function () {
    return view('privacy');
});
Route::get('terms', function () {
    return view('terms');
});
Route::get('aboutus', function () {
    return view('aboutus');
});
Route::get('support', function () {
    return view('support');
});
Route::get('contactus', function () {
    return view('contactus');
});
Route::get('faq', function () {
    return view('faq');
});
Route::get('share', function () {
    return view('share');
});
Route::get('signup', [signupController:: class, 'index'])->name('general.signup');
Route::post('signup', [signupController:: class, 'signup'])->name('general.signuppost');
Route::get('validateotp', [signupController:: class, 'validateotp'])->name('general.validateotp');
Route::post('validateotp', [signupController:: class, 'postvalidateotp'])->name('general.postvalidateotp');
Route::post('resend-otp', [signupController:: class, 'resendotp'])->name('general.resendotp');
Route::get('subscribe', [signupController:: class, 'subscribe'])->name('general.subscribe');
Route::post('subscribe', [signupController:: class, 'subscribestripe'])->name('general.subscribepost');

Route::get('logindata', [LoginController::class, 'indexuserlogin'])->name('general.login');
Route::get('logindata/{type}', [LoginController::class, 'indexuserlogin'])->name('general.loginwithtype');

Route::get('download-pdf/{user_id}/{month}/{year}', function($user_id,$month,$year){
    $firstdayofmonth = $year.'-'.$month.'-01 00:00:00';
    $lastdayofmonth = $year.'-'.$month.'-31 23:59:59';
    $results = DB::select('SELECT sum(t.amount) as totalamount,cl.title,t.type FROM `transactions` t join category_lists cl on cl.id=t.category_list_id WHERE t.`user_id`=? and t.transaction_date between ? and ? group by t.category_list_id,cl.title,t.type', [$user_id,$firstdayofmonth,$lastdayofmonth]);
    $userdetails = DB::select('SELECT name FROM users WHERE id=?',[$user_id]);

    $incomeArr = [];
    $expArr = [];
    $totalIncome = 0;
    $totalexpenditure = 0;
    $givenDate = Carbon::parse($year.'-'.$month.'-01');
    $lastDate = $givenDate->endOfMonth()->format('d-m-Y');
    
    for($i=0;$i<count($results);$i++){
        $amount = $results[$i]->totalamount;
        $results[$i]->totalamount = number_format($results[$i]->totalamount,2);
        if($results[$i]->type == 'income'){
            $incomeArr[] = $results[$i];
            $totalIncome = $totalIncome + $amount;
        }else{
            $expArr[] = $results[$i];
            $totalexpenditure = $totalexpenditure+$amount;
        }
    }
    $netIncome = $totalIncome - $totalexpenditure;
    $data = [ 'user'=>$userdetails[0]->name,'income'=>$incomeArr,'expense'=>$expArr, 'totalincome'=>number_format($totalIncome,2),'totalexpenditure'=>number_format($totalexpenditure,2),'netincome'=>number_format($netIncome,2),'start_date'=>Carbon::create($year, $month, 01, 0, 0, 0)->format('d-m-Y'),'end_date'=>$lastDate ]; // Pass your data
    $pdf = Pdf::loadView('report', $data); // Your Blade view
    return $pdf->stream('report.pdf'); // This opens in browser
});
// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

Route::group(['prefix' => 'admin'],function(){
    Route::group(['middleware'=>'admin.guest'],function(){
        Route::get('login', [LoginController::class, 'index'])->name('admin.login');
        Route::get('resubscribe', [SubscriptionController::class, 'resubscribe'])->name('admin.resubscribe');
        Route::post('resubscribepost', [SubscriptionController::class, 'resubscribepost'])->name('admin.resubscribepost');
        Route::post('authenticate', [LoginController::class, 'authenticate'])->name('admin.authenticate');
        Route::post('authenticateuser', [LoginController::class, 'authenticateuser'])->name('admin.authenticateuser');
        
    });
    Route::group(['middleware'=>'admin.auth'],function(){
        Route::get('logout', [LoginController::class, 'logout'])->name('admin.logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('users', [CustomerController::class, 'index'])->name('admin.customer');
        Route::post('user/suspend', [CustomerController::class, 'suspend'])->name('admin.suspendcustomer');
        Route::get('users/{userid}', [CustomerController::class, 'edituser'])->name('admin.editcustomer');
        
        Route::post('searchusers', [CustomerController::class, 'index'])->name('admin.searchcustomer');
        Route::get('transactions', [TransactionController::class, 'index'])->name('admin.transactions');
        Route::get('transactions/{transactionid}', [TransactionController::class, 'getTransaction'])->name('admin.transactionsidwise');
        //Route::post('transactions', [TransactionController::class, 'index'])->name('admin.searchtransactions');
        Route::get('millages', [MillageController::class, 'index'])->name('admin.millage');
        Route::get('millages/{millageId}', [MillageController::class, 'getMillage'])->name('admin.millageidwise');
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions');
        //Route::get('millages/{userid}', [MillageController::class, 'index'])->name('admin.millageuserwise');
        //Route::post('millages', [MillageController::class, 'index'])->name('admin.searchmillage');
        Route::get('emailtemplates', [EmailTemplateController::class, 'index'])->name('admin.emailtemplate');
        Route::post('emailtemplates', [EmailTemplateController::class, 'index'])->name('admin.searchemailtemplate');
        Route::get('emailtemplates/{templateId}', [EmailTemplateController::class, 'getTemplate'])->name('admin.getemailtemplate');
        Route::post('emailtemplates/edit', [EmailTemplateController::class, 'edit'])->name('admin.editemailtemplate');
        Route::get('categories', [CategoryListController::class, 'index'])->name('admin.categories');
        Route::get('category/add', [CategoryListController::class, 'addcategory'])->name('admin.addcategory');
        Route::post('category/add', [CategoryListController::class, 'individualCategoryAdd'])->name('admin.postaddcategories');
        Route::get('categories/{catid}', [EditCategoryController::class, 'getcategory'])->name('admin.editcategories');
        Route::post('categories/{catid}', [EditCategoryController::class, 'updatecategories'])->name('admin.updatedategories');
    });
});


require __DIR__.'/auth.php';
