<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class TransactionController extends Controller
{
    //
    public function index(Request $request){

        $input = $request->all();
        if(isset($request->u)){
            $user = User::where('id',$request->u)->first();
        }
        $query = Transaction::join('category_lists','category_lists.id','=','transactions.category_list_id')->where('transactions.status','1')->join('users','users.id','=','transactions.user_id')
        ->selectRaw("transactions.id,transactions.title,transactions.user_id,transactions.amount,transactions.type,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/transaction_images/',transactions.document)) as document,transactions.status,transactions.paymentmethod,transactions.transaction_date,category_lists.title as catecory_name,transactions.category_list_id,users.name");
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $query->whereBetween('transactions.transaction_date', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $query->where('transactions.transaction_date', '>=', $request->start_date.' 00:00:00');
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $query->where('transactions.transaction_date', '<=', $request->to_date.' 23:59:59');
        });
        $query->when(!$request->has('start_date') && !$request->input('end_date'), function ($query) use ($request) {
            $today = Carbon::now();
            $dateFrom = $today->format('Y').'-'.$today->format('m').'-1';
            $query->where('transactions.transaction_date','>=', $dateFrom.' 00:00:00');
        });
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('transactions.title','like', '%' .  $request->str_search . '%');
                $query->orWhere('transactions.amount','like', '%' .  $request->str_search . '%');
                $query->orWhere('transactions.paymentmethod','like', '%' .  $request->str_search . '%');
                $query->orWhere('category_lists.title','like', '%' .  $request->str_search . '%');
                $query->orWhere('users.name','like', '%' .  $request->str_search . '%');
                $query->orWhere('transactions.type','like', '%' .  $request->str_search . '%');
            });
        });
        $query->when(isset($request->u), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('transactions.user_id', $request->u );
            });
        });
        $queryraw = $query;
        $query->orderBy('transaction_date', 'desc');
        $transactions = $query->paginate(20);
        $totalrecord = $queryraw->get();
        $totalIncome = 0;
        $totalExpenses = 0;
        $totalProfit = 0;
        for($i=0; $i<sizeof($totalrecord);$i++)
        {
            if($totalrecord[$i]->type == 'income'){
                $totalIncome = $totalIncome+$totalrecord[$i]->amount;
                $totalProfit = $totalProfit+$totalrecord[$i]->amount;
            }elseif($totalrecord[$i]->type == 'expenses'){
                $totalExpenses = $totalExpenses+$totalrecord[$i]->amount;
                $totalProfit = $totalProfit-$totalrecord[$i]->amount;
            }
        }
        return view('admin.transactions',['transaction'=>$transactions,'user'=>(isset($user))?$user: null,'totalIncome'=>number_format($totalIncome,2),'totalExpenses'=>number_format($totalExpenses,2),'totalProfit'=>number_format($totalProfit,2)]);
    }

    public function getTransaction($transactionId){
        $query = Transaction::join('category_lists','category_lists.id','=','transactions.category_list_id')->where('transactions.status','1')->join('users','users.id','=','transactions.user_id')
        ->selectRaw("transactions.id,transactions.title,transactions.user_id,transactions.amount,transactions.type,IFNULL(null,CONCAT('".config('app.images_path')."transaction_images/',transactions.document)) as document,transactions.status,transactions.paymentmethod,transactions.transaction_date,category_lists.title as catecory_name,transactions.category_list_id,users.name,transactions.is_recurring,transactions.recurring_period")
        ->where('transactions.id',$transactionId);
        $transactions = $query->first();
        return view('admin.gettransaction',['transaction'=>$transactions]);
    }
}
