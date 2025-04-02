<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Millage;
use App\Models\User;
use Carbon\Carbon;

class MillageController extends Controller
{
    //
    public function index(Request $request){
        if(isset($request->u)){
            $user = User::where('id',$request->u)->first();
        }
        $query = Millage::join('users','users.id','=','millages.user_id')
        ->selectRaw("millages.id,millages.business_millage,millages.personal_millage,millages.millage_date,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/millage_images/',millages.document)) as document,users.name"); 
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $query->whereBetween('millage_date', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $query->where('millage_date', '>=', $request->start_date.' 00:00:00');
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $query->where('millage_date', '<=', $request->to_date.' 23:59:59');
        });
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('business_millage','like', '%' .  $request->str_search . '%');
                $query->orWhere('personal_millage','like', '%' .  $request->str_search . '%');
                $query->orWhere('users.name','like', '%' .  $request->str_search . '%');
            });
        });
        $queryraw = $query;
        $query->orderBy('millage_date', 'desc');
        $millage = $query->paginate(20);
        $totalrecord = $queryraw->get();
        $totalpersonalmillage = 0;
        $totalbusinessmillage = 0;
        for($i=0; $i<sizeof($totalrecord);$i++)
        {
            $totalpersonalmillage = $totalpersonalmillage+ $totalrecord[$i]->personal_millage;
            $totalbusinessmillage = $totalbusinessmillage+ $totalrecord[$i]->business_millage;
        }
        return view('admin.millage',['millage'=>$millage,'user'=>(isset($user))?$user: null,'totalpersonalmillage'=>$totalpersonalmillage,'totalbusinessmillage'=>$totalbusinessmillage] );
    }

    public function getMillage($millageId){
        $query = Millage::join('users','users.id','=','millages.user_id')
        ->selectRaw("millages.id,millages.business_millage,millages.user_id,millages.personal_millage,IFNULL(null,CONCAT('".config('app.images_path')."millage_images/',millages.document)) as document,millages.millage_date,users.name")
        ->where('millages.id',$millageId);
        $millages = $query->first();
        return view('admin.getmillage',['millages'=>$millages]);
    }
}
