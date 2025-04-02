<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CategoryList;
use Illuminate\Support\Facades\Validator;

class EditCategoryController extends Controller
{
    //
    public function getcategory($catid){
        $category = CategoryList::where('id',$catid)->first();
        return view('admin.editcategories',['category'=>$category]);
    }

    public function updatecategories(Request $request){
        $rules = array(
            'title'=>'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return redirect()->route('admin.editcategories',['catid'=>$request->id])->withInput()->withErrors($validation);
        }else{
            $input = $request->except('_token');
            CategoryList::where('id',$request->id)->update($input);
            return redirect()->route('admin.editcategories',['catid'=>$request->id])->withInput()->with('success','Data updated successfully.');
        }
    }
}
