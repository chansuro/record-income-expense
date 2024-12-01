<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($user_id)
    {
        //
        $Transaction = Transaction::where('status','1')
                                        ->where('user_id',$user_id)
                                        ->get();
        
        if(!count($Transaction)>0){
            return ['response'=>false, 'msg'=>'No record found!'];
        }
        //
        return $Transaction;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        $rules = array(
            'title'=>'required',
            'type'=> 'required',
            'user_id'=> 'required',
            'amount'=> 'required | decimal:1',
            'document' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
            'paymentmethod'=> 'required',
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
                //$request->document->move(public_path('transaction_images'), $createnewFileName); //local path
                $request->document->move('transaction_images', $createnewFileName);
                $input['document'] = $createnewFileName;
                $Transaction = Transaction::create($input);
                return ['response'=>true, 'msg'=>'Transaction added successfully!'];
            }
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
    public function edit(transaction $transaction)
    {
        //
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
    public function destroy(transaction $transaction)
    {
        //
    }
}
