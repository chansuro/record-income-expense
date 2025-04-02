<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    //
    public function index(Request $request){
        $query = EmailTemplate::when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('subject','like', '%' .  $request->str_search . '%');
                $query->orWhere('body','like', '%' .  $request->str_search . '%');
                $query->orWhere('key','like', '%' .  $request->str_search . '%');
            });
        })->where('type','Email')->orwhere('type','Otp');

        $templates = $query->paginate(20);
        return view('admin.emailtemplate',['template'=>$templates]);
    }

    public function getTemplate($templateId){
        $query = EmailTemplate::where('id',$templateId)->first();
        $emailKeywordsArr = config('app.email_template_var');
        return view('admin.getemailtemplate',['template'=>$query,'templatevariables'=>$emailKeywordsArr]);
    }

    public function edit(Request $request){
        if($request->type == 'Email'){
            $rules = array(
                'subject'=>'required' ,
                'body'=> 'required',
            );
        }else{
            $rules = array(
                'body'=> 'required',
            );
        }
        
        
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return redirect()->route('admin.getemailtemplate',['templateId'=>$request->id])->withInput()->withErrors($validation);
        }else{
            $input = $request->except('_token');
            EmailTemplate::where('id',$request->id)->update($input);
            return redirect()->route('admin.getemailtemplate',['templateId'=>$request->id])->withInput()->with('success','Data updated successfully.');
        }
    }
}
