<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class CategoryListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($type,$user_id)
    {
        $categoryList = CategoryList::where('type',$type)
                                        ->where('status','1')
                                        ->whereNull('user_id')
                                        ->orwhere('user_id',$user_id)
                                        ->get();
        
        //
        return $categoryList;
        //return $request->type;
    }

    public function individualCategoryAdd(Request $request){
        $rules = array(
            'title'=>'required',
            'type'=> 'required',
            'user_id'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $input["status"] = 1; 
            $categoryList = CategoryList::create($input);
            return ['response'=>true, 'msg'=>'Category added successfully!'];
        }
    }

    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(StoreCategoryListRequest $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(CategoryList $categoryList)
    // {
    //     //
    // }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(CategoryList $categoryList)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(UpdateCategoryListRequest $request, CategoryList $categoryList)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(CategoryList $categoryList)
    // {
    //     //
    // }
}
