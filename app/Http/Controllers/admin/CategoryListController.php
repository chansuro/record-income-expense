<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CategoryList;
use Illuminate\Support\Facades\Validator;

//use Mailgun\Mailgun;

class CategoryListController extends Controller
{
    //
    public function index(Request $request){
        $query = CategoryList::
        leftJoin('users', function($join) {
            $join->on('users.id', '=', 'category_lists.user_id')
                 ->where('category_lists.user_id', '!=', null);
        })->select('category_lists.id','category_lists.title','category_lists.type','users.name');
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('category_lists.title','like', '%' .  $request->str_search . '%');
                $query->orWhere('category_lists.type','like', '%' .  $request->str_search . '%');
                $query->orWhere('users.name','like', '%' .  $request->str_search . '%');
            });
        });

        $query->where('category_lists.status','1');
        $categorylist =  $query->paginate(20);
        return view('admin.categorylist',['categorylist'=>$categorylist] );
    }

    public function addcategory(Request $request){
        return view('admin.addcategories' );
    }
    public function individualCategoryAdd(Request $request){
        $rules = array(
            'title'=>'required',
            'type'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        
        if($validation->fails()){
            return redirect()->route('admin.addcategory')->withInput()->withErrors($validation);
        }else{
            $input = $request->all();
            $categoryList = CategoryList::create($input);
            return redirect()->route('admin.addcategory')->withInput()->with('success','Category added successfully.');
        }
    }
}
