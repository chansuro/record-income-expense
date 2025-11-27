<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Millage;
use App\Models\CategoryList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Google\Cloud\Storage\StorageClient;
use App\Jobs\ProcessImageUpload;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        //
        $today = Carbon::now();
        
        $input = $request->all();
        $query = Transaction::join('category_lists','category_lists.id','=','transactions.category_list_id')->where('transactions.status','1')
                        ->selectRaw("transactions.id,transactions.title,transactions.user_id,transactions.amount,transactions.type,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/transaction_images/',transactions.document)) as document,transactions.status,transactions.paymentmethod,transactions.transaction_date,category_lists.title as catecory_name,transactions.category_list_id,transactions.is_recurring,transactions.recurring_period")
                        ->where('transactions.user_id',$input['user_id']);
        $query->when((isset($input['from_date']) && isset($input['to_date'])), function ($query) use ($input) {
            $transactionFromDate = explode("-",$input['from_date']);
            $input['from_date'] = $transactionFromDate[2].'-'.$transactionFromDate[1].'-'.$transactionFromDate[0];
            $transactionToDate = explode("-",$input['to_date']);
            $input['to_date'] = $transactionToDate[2].'-'.$transactionToDate[1].'-'.$transactionToDate[0];
            $query->whereBetween('transactions.transaction_date', [$input['from_date'].' 00:00:00', $input['to_date'].' 23:59:59']);
        });
        $query->when(isset($input['from_date']), function ($query) use ($input) {
            $transactionFromDate = explode("-",$input['from_date']);
            $input['from_date'] = $transactionFromDate[2].'-'.$transactionFromDate[1].'-'.$transactionFromDate[0];
            $query->where('transactions.transaction_date', '>=', $input['from_date'].' 00:00:00');
        });
        $query->when(isset($input['to_date']), function ($query) use ($input) {
            $transactionToDate = explode("-",$input['to_date']);
            $input['to_date'] = $transactionToDate[2].'-'.$transactionToDate[1].'-'.$transactionToDate[0];
            $query->where('transactions.transaction_date', '<=', $input['to_date'].' 23:59:59');
        });
        $query->when((!isset($input['from_date']) && !isset($input['to_date'])), function ($query) use ($today) {
            $dateString = $today->format('Y').'-'.$today->format('m').'-%';
            $query->where('transactions.transaction_date', 'like', $dateString);
        });
        $query->when((isset($input['category'])), function ($query) use ($input) {
                $query->where('transactions.category_list_id', $input['category']);
        });
        $query->when((isset($input['paymentmethod'])), function ($query) use ($input) {
                $query->where('transactions.paymentmethod', $input['paymentmethod']);
        });
        $query->when((isset($input['recurring'])), function ($query) use ($input) {
                $query->where('transactions.is_recurring', 'Y');
        });
        $query->orderBy('transaction_date', 'desc');
        $offset = $input['offset'];
        $limit = 40;
        $transactionCount = $query->count();
        $Transaction = $query->offset($offset*$limit)->limit($limit)->get();
        if(($offset*$limit) + $limit >=$transactionCount){
            $eor = true;
        }else{
            $eor = false;
        }
        if(!$transactionCount>0){
            return ['response'=>false, 'msg'=>'No record found!'];
        }

        //
        return ['response'=>true, 'data'=>$Transaction,'offset'=>$offset+1,'endofrecord'=>$eor, 'previouspage'=>(($offset-1)>=0)?($offset-1):null];
    }

    /**
     * Display a listing of the resource.
     */
    public function datacurrentmonth($user_id)
    {
        $today = Carbon::now();
        $dateSearchCurrent = $today->format('Y').'-'.$today->format('m').'-%';

        $transactions = Transaction::select('transactions.category_list_id',DB::raw('sum(transactions.amount) as total'))
        ->where('transactions.user_id',$user_id)
        ->where('transactions.transaction_date', 'like', $dateSearchCurrent.'%')
        ->groupBy('transactions.category_list_id')
        ->get();

        $totalIncome = 0;
        $totalExpenses = 0;
        $totalProfit = 0;
        for($i=0; $i<sizeof($transactions);$i++)
        {
            $categoryList = CategoryList::select('title','type')->where('id',$transactions[$i]['category_list_id'])
                ->where('status','1')
                ->get();
                if($categoryList[0]['type'] == 'income'){
                    $transactions[$i]['type'] = $categoryList[0]['type'];
                    $totalIncome = $totalIncome+$transactions[$i]['total'];
                }elseif($categoryList[0]['type'] == 'dailyexp' || $categoryList[0]['type'] == 'recurringexp'){
                    $transactions[$i]['type'] = 'expenses';
                    $totalExpenses = $totalExpenses+$transactions[$i]['total'];
                } 

                $transactions[$i]['title'] = $categoryList[0]['title'];
                $transactions[$i]['total'] = number_format($transactions[$i]['total'],2);
        }

        $millage = Millage::select(DB::raw('sum(business_millage) as business_millage'),DB::raw('sum(personal_millage) as personal_millage'))
        ->where('user_id',$user_id)
        ->where('millage_date', 'like', $dateSearchCurrent.'%')
        ->get();

        return ['transactions'=>$transactions,'totalincome'=>number_format($totalIncome,2),'totalexpenses'=>number_format($totalExpenses,2),'totalprofit'=>number_format(($totalIncome-$totalExpenses),2),'business_millage'=>($millage[0]->business_millage)? $millage[0]->business_millage : 0.0,'personal_millage'=>($millage[0]->personal_millage) ? $millage[0]->personal_millage : 0.0];
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        $rules = array(
            'type'=> 'required',
            'user_id'=> 'required',
            'amount'=> 'required | numeric',
            'paymentmethod'=> 'required',
            'transaction_date'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            if($request->hasFile('document')) {
                $filename = $request->file('document')->getClientOriginalName(); // get the file name
                $getfilenamewitoutext = pathinfo($filename, PATHINFO_FILENAME); // get the file name without extension
                $getfileExtension = $request->file('document')->getClientOriginalExtension(); // get the file extension
                $createnewFileName = time().'_'.str_replace(' ','_', $getfilenamewitoutext).'.'.$getfileExtension; // create new random file name
                // $request->document->move(public_path('transaction_images'), $createnewFileName); //local path
                // //$request->document->move(config('app.transaction_img_path'), $createnewFileName);
                
                // putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                // $storage = new StorageClient();
                // $bucket = $storage->bucket(config('services.googlecloud.bucket'));
                // $filePath = public_path('transaction_images').'/'.$createnewFileName;
                // $objectName = $createnewFileName;
                // $path = $bucket->upload(
                //     fopen($filePath, 'r'), // Open the file in read mode
                //     [
                //         'name' => 'transaction_images/'.$objectName // Set the file name in the bucket
                //     ]
                // );
                // $object = $bucket->object('transaction_images/'.$objectName);
                // $object->update([
                //     'acl' => [
                //         ['entity' => 'allUsers', 'role' => 'READER']
                //     ]
                // ]);
                // $image_path = config('app.transaction_img_path')."/{$createnewFileName}";
                // if (File::exists($image_path)) {
                //     unlink($image_path);
                // }
                //$publicUrl = env('GOOLE_PUBLIC_URL').env("GOOGLE_CLOUD_STORAGE_BUCKET")."/transaction_images/{$objectName}";
                $image = $request->file('document');
                $tempPath = $image->store('temp', 'public'); // temporarily store
                $input['document'] = $createnewFileName;
                ProcessImageUpload::dispatch($tempPath, $createnewFileName);
            }
            $transactionDate = explode("-",$input['transaction_date']);
            $input['transaction_date'] = $transactionDate[2].'-'.$transactionDate[1].'-'.$transactionDate[0].' '.date("H:i:s");;
            
            if($input['type'] != 'income')
            {
                if($input['type'] == 'dailyexp' || $input['type'] == 'recurringexp'){
                    $input['type'] = 'expenses';
                }
            }

            //$input['amount'] = number_format($input['amount'],2,'.','');
            $Transaction = Transaction::create($input);
            return ['response'=>true, 'msg'=>'Transaction added successfully!'];
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoretransactionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(transaction $transaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        $rules = array(
            'id'=>'required',
            'type'=> 'required',
            'user_id'=> 'required',
            'amount'=> 'required | numeric',
            'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:10240',
            'paymentmethod'=> 'required',
            'transaction_date'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($transaction->id >0){

                if($request->hasFile('document')) {
                    putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                    $storage = new StorageClient();
                    $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                    if($transaction->document !=''){
                        $object = $bucket->object('transaction_images/'.$transaction->document);
                        if($object->exists()){
                            $object->delete();
                        }
                    }

                    $filename = $request->file('document')->getClientOriginalName(); // get the file name
                    $getfilenamewitoutext = pathinfo($filename, PATHINFO_FILENAME); // get the file name without extension
                    $getfileExtension = $request->file('document')->getClientOriginalExtension(); // get the file extension
                    $createnewFileName = time().'_'.str_replace(' ','_', $getfilenamewitoutext).'.'.$getfileExtension; // create new random file name
                    // $request->document->move(config('app.transaction_img_path'), $createnewFileName);

                    // $filePath = public_path('transaction_images').'/'.$createnewFileName;
                    // $objectName = $createnewFileName;
                    // $path = $bucket->upload(
                    //     fopen($filePath, 'r'), // Open the file in read mode
                    //     [
                    //         'name' => 'transaction_images/'.$objectName // Set the file name in the bucket
                    //     ]
                    // );
                    // $object = $bucket->object('transaction_images/'.$objectName);
                    // $object->update([
                    //     'acl' => [
                    //         ['entity' => 'allUsers', 'role' => 'READER']
                    //     ]
                    // ]);
                    // $image_path = config('app.transaction_img_path')."/{$createnewFileName}";
                    // if (File::exists($image_path)) {
                    //     unlink($image_path);
                    // }
                    // //$publicUrl = env('GOOLE_PUBLIC_URL').env("GOOGLE_CLOUD_STORAGE_BUCKET")."/transaction_images/{$objectName}";
                    // $input['document'] = $createnewFileName;
                    $image = $request->file('document');
                    $tempPath = $image->store('temp', 'public'); // temporarily store
                    $input['document'] = $createnewFileName;
                    ProcessImageUpload::dispatch($tempPath, $createnewFileName);
                }
                $transactionDate = explode("-",$input['transaction_date']);
                $input['transaction_date'] = $transactionDate[2].'-'.$transactionDate[1].'-'.$transactionDate[0].' '.date("H:i:s");;
                $Transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Transaction edited successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }

    }

    public function removerecurring(Request $request)
    {
        $rules = array(
            'id'=>'required',
            'is_recurring'=>'required',
            'recurring_period'=>'required',
            'user_id'=>'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($transaction->id >0){
                $input['is_recurring'] = $input['is_recurring'];
                $input['recurring_period'] = $input['recurring_period'];
                $Transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Transaction edited successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }

    }

    public function removeTransactionImage(Request $request){
        $rules = array(
            'id'=>'required',
            'user_id'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($transaction->id >0){
                    if($transaction->document !=''){
                        putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                        $storage = new StorageClient();
                        $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                        if($transaction->document !=''){
                            $object = $bucket->object('transaction_images/'.$transaction->document);
                            if($object->exists()){
                                $object->delete();
                            }
                        }
                    }
                $input['document'] = null;
                $Transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Transaction image removed successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatetransactionRequest $request, transaction $transaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $rules = array(
            'id'=>'required',
            'user_id'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($transaction->id >0){
                if($transaction->document !=''){
                    putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                    $storage = new StorageClient();
                    $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                    if($transaction->document !=''){
                        $object = $bucket->object('transaction_images/'.$transaction->document);
                        if($object->exists()){
                            $object->delete();
                        }
                    }
                }
                $Transaction = Transaction::where('id',$input['id'])->where('user_id',$input['user_id'])->delete();
                return ['response'=>true, 'msg'=>'Transaction deleted successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

    public function listrecurring(){

    }
    public function depreciationCalculator(Request $request)
    {
        $rules = array(
            'assetvalue'=> 'required | numeric',
            'salvagevalue'=> 'required | numeric',
            'depreciationyears'=> 'required | numeric'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            if($input['salvagevalue'] >= $input['assetvalue']){
                return ['response'=>false, 'msg'=>'Asset cost must be greater than Salvage value!'];
            }
            $bookValue = $input['assetvalue'];
            $totalDepreciation  = $bookValue - $input['salvagevalue'];
            $yearlyDepreciation = $totalDepreciation/$input['depreciationyears'];
            $depreciationPercentage = ($yearlyDepreciation/$totalDepreciation)*100;

            $DepretioationCalculationArr = [];
            $AccumulatedDepreciation = 0;
            for($i=0;$i<$input['depreciationyears'];$i++)
            {
                $bookValue = ($i == 0)? $bookValue : $bookValue - $yearlyDepreciation;
                $endingbookValue = $bookValue - $yearlyDepreciation;
                $AccumulatedDepreciation  = $AccumulatedDepreciation + $yearlyDepreciation;

                $DepretioationCalculationArr[$i]['starting_book_value'] = $bookValue;
                $DepretioationCalculationArr[$i]['ending_book_value'] = $endingbookValue;
                $DepretioationCalculationArr[$i]['depreciation_amount'] = $yearlyDepreciation;
                $DepretioationCalculationArr[$i]['accumulated_depreciation'] = $AccumulatedDepreciation;
                $DepretioationCalculationArr[$i]['depreciation_percentage'] = number_format($depreciationPercentage,2);

            }
            return ['response'=>true, 'data'=>$DepretioationCalculationArr];
        }
    }
}
