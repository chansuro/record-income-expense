<?php

namespace App\Http\Controllers;

use App\Models\Millage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MillageController extends Controller
{
    //
    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        $rules = array(
            'business_millage'=>'required' ,
            'personal_millage'=> 'required',
            'millage_date'=> 'required',
            'user_id'=> 'required',
            'document' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
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
                $request->document->move('millage_images', $createnewFileName);
                $input['document'] = $createnewFileName;
                $Transaction = Millage::create($input);
                return ['response'=>true, 'msg'=>'Millage added successfully!'];
            }
        }
    }
}
