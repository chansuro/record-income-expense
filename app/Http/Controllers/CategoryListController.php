<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class CategoryListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($type,$user_id=null)
    {

        ($type == 'expense') ? $type = 'exp' : $type = $type;
        // $categoryList = CategoryList::where(function ($query) use ($user_id) {
        //                         $query->where('user_id', '=', null)
        //                         ->orWhere('user_id', '=', $user_id);
        //                         })->where('type','like','%'.$type.'%')
        //                         ->where('status','1')->get();

        $categoryList = CategoryList::where(function ($query) use ($user_id) {
                                    $query->where('user_id', '=', null)
                                    ->orWhere('user_id', '=', $user_id);
                                    })->where('status','1');

        $categoryList->when((($type == 'exp' || $type == 'income') && $type != 'paymentmothod'), function ($categoryList) use ($type) {
            $categoryList->where(function ($categoryList) use ($type) {
                $categoryList->where('type','like', '%' .  $type . '%');
            });
        });
        $categoryList->when(($type == 'paymentmethod' && $user_id > 0), function ($categoryList) use ($type) {
            $categoryList->where(function ($categoryList) use ($type) {
                $categoryList->where('type',  $type );
            });
        });
        $categoryList->when(($type == 'paymentmethod' && ! $user_id > 0), function ($categoryList) use ($type) {
            $categoryList->where(function ($categoryList) use ($type) {
                $categoryList->where('type','like', '%' .  $type . '%' );
            });
        });
        // $categoryList->when(($type == 'paymentmothod' && !$user_id > 0), function ($categoryList) use ($type) {
        //     $categoryList->where(function ($categoryList) use ($type) {
        //         $categoryList->where('type', $type );
        //     });
        // });
        $categoryList = $categoryList->get();
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
        }
        else{
            $input = $request->all();
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
    public function edit(Request $request)
    {
        //
        $rules = array(
            'title'=>'required',
            'type'=> 'required',
            'user_id'=> 'required',
            'id'=>'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $category = CategoryList::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($category->id >0){
                $category = CategoryList::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Category edited successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

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
    public function destroy($category_id,$user_id)
    {
        DB::table('category_lists')->where('id', $category_id)->where('user_id', $user_id)->delete();
        return ['response'=>true, 'msg'=>'Category deleted successfully!'];
    }
}
