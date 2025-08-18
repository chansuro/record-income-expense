<?php

namespace App\Http\Controllers;

use App\Models\Millage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Google\Cloud\Storage\StorageClient;
use App\Jobs\ProcessMillageImageUpload;

class MillageController extends Controller
{
    //
    public function index(Request $request)
    {
        //
        $today = Carbon::now();
        
        $input = $request->all();
        if(isset($input['from_date']) || isset($input['to_date'])){
            if(isset($input['from_date']) && isset($input['to_date'])){
                $millageFromDate = explode("-",$input['from_date']);
                $input['from_date'] = $millageFromDate[2].'-'.$millageFromDate[1].'-'.$millageFromDate[0];

                $millageToDate = explode("-",$input['to_date']);
                $input['to_date'] = $millageToDate[2].'-'.$millageToDate[1].'-'.$millageToDate[0];

                $BusinessMillageTotal = DB::table('millages')
                    ->where('user_id',$input['user_id'])
                    ->whereBetween('millage_date', [$input['from_date'].' 00:00:00', $input['to_date'].' 23:59:59'])
                    ->sum('business_millage');
        
                $personalMillageTotal = DB::table('millages')
                            ->where('user_id',$input['user_id'])
                            ->whereBetween('millage_date', [$input['from_date'].' 00:00:00', $input['to_date'].' 23:59:59'])
                            ->sum('personal_millage');
                $millageCurrentMonth = array(
                    'business'=>$BusinessMillageTotal,
                    'personal' => $personalMillageTotal
                );
                $Millage = Millage::selectRaw("millages.id,millages.business_millage,millages.personal_millage,millages.millage_date,millages.user_id,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/millage_images/',millages.document)) as document,millages.created_at as created_at")
                                                ->where('millages.user_id',$input['user_id'])
                                                ->whereBetween('millages.millage_date', [$input['from_date'].' 00:00:00', $input['to_date'].' 23:59:59'])
                                                ->get();
            }elseif(isset($input['from_date'])){
                $millageFromDate = explode("-",$input['from_date']);
                $input['from_date'] = $millageFromDate[2].'-'.$millageFromDate[1].'-'.$millageFromDate[0];
                $BusinessMillageTotal = DB::table('millages')
                    ->where('user_id',$input['user_id'])
                    ->where('millage_date', '>=', $input['from_date'].' 00:00:00')
                    ->sum('business_millage');
        
                $personalMillageTotal = DB::table('millages')
                            ->where('user_id',$input['user_id'])
                            ->where('millage_date', '>=', $input['from_date'].' 23:59:59')
                            ->sum('personal_millage');
                $millageCurrentMonth = array(
                    'business'=>$BusinessMillageTotal,
                    'personal' => $personalMillageTotal
                );
                $Millage = Millage::selectRaw("millages.id,millages.business_millage,millages.personal_millage,millages.millage_date,millages.user_id,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/millage_images/',millages.document)) as document,millages.created_at as created_at")
                                                ->where('millages.user_id',$input['user_id'])
                                                ->where('millage_date', '>=', $input['from_date'].' 00:00:00')
                                                ->get();
            }
            elseif(isset($input['to_date'])){
                $millageToDate = explode("-",$input['to_date']);
                $input['to_date'] = $millageToDate[2].'-'.$millageToDate[1].'-'.$millageToDate[0];
                $BusinessMillageTotal = DB::table('millages')
                    ->where('user_id',$input['user_id'])
                    ->where('millage_date', '<=', $input['to_date'].' 23:59:59')
                    ->sum('business_millage');
        
                $personalMillageTotal = DB::table('millages')
                            ->where('user_id',$input['user_id'])
                            ->where('millage_date', '<=', $input['to_date'].' 23:59:59')
                            ->sum('personal_millage');
                $millageCurrentMonth = array(
                    'business'=>$BusinessMillageTotal,
                    'personal' => $personalMillageTotal
                );
                $Millage = Millage::selectRaw("millages.id,millages.business_millage,millages.personal_millage,millages.millage_date,millages.user_id,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/millage_images/',millages.document)) as document,millages.created_at as created_at")
                                                ->where('millages.user_id',$input['user_id'])
                                                ->where('millage_date', '<=', $input['to_date'].' 23:59:59')
                                                ->get();
            }
            
            
        }else{
            $dateString = $today->format('Y').'-'.$today->format('m').'-%';
            $BusinessMillageTotal = DB::table('millages')
                    ->where('user_id',$input['user_id'])
                    ->where('millage_date', 'like', $dateString)
                    ->sum('business_millage');
            $personalMillageTotal = DB::table('millages')
                        ->where('user_id',$input['user_id'])
                        ->where('millage_date', 'like', $dateString)
                        ->sum('personal_millage');
            $millageCurrentMonth = array(
                'business'=>$BusinessMillageTotal,
                'personal' => $personalMillageTotal
            );
            $Millage = Millage::selectRaw("millages.id,millages.business_millage,millages.personal_millage,millages.millage_date,millages.user_id,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/millage_images/',millages.document)) as document,millages.created_at as created_at")
                                            ->where('millages.user_id',$input['user_id'])
                                            ->where('millage_date', 'like', $dateString)
                                            ->get();
        }
        
        
        
        if(!count($Millage)>0){
            return ['response'=>false, 'msg'=>'No record found!','millage'=>$millageCurrentMonth];
        }
        //
        return ['response'=>true, 'data'=>$Millage,'millage'=>$millageCurrentMonth,'input'=>$input];
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        $rules = array(
            'business_millage'=>'required' ,
            'millage_date'=> 'required',
            'user_id'=> 'required',
            'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:10240',
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
                // $request->document->move('millage_images', $createnewFileName);

                // putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                // $storage = new StorageClient();
                // $bucket = $storage->bucket(config('services.googlecloud.bucket'));
                // $filePath = public_path('millage_images').'/'.$createnewFileName;
                // $objectName = $createnewFileName;
                // $path = $bucket->upload(
                //     fopen($filePath, 'r'), // Open the file in read mode
                //     [
                //         'name' => 'millage_images/'.$objectName // Set the file name in the bucket
                //     ]
                // );
                // $object = $bucket->object('millage_images/'.$objectName);
                // $object->update([
                //     'acl' => [
                //         ['entity' => 'allUsers', 'role' => 'READER']
                //     ]
                // ]);

                // $image_path = config('app.millage_img_path')."/{$createnewFileName}";
                // if (File::exists($image_path)) {
                //     unlink($image_path);
                // }
                $image = $request->file('document');
                $tempPath = $image->store('temp', 'public'); // temporarily store
                $input['document'] = $createnewFileName;
                ProcessMillageImageUpload::dispatch($tempPath, $createnewFileName);

                $input['document'] = $createnewFileName;
            }

            if(!isset($input['personal_millage']))
            $input['personal_millage'] = 0;
            $millageDate = explode("-",$input['millage_date']);
            $input['millage_date'] = $millageDate[2].'-'.$millageDate[1].'-'.$millageDate[0];
            $Millage = Millage::create($input);
            return ['response'=>true, 'msg'=>'Millage added successfully!'];
        }
    }

    public function edit(Request $request)
    {
        $rules = array(
            'id'=>'required',
            'business_millage'=>'required' ,
            'millage_date'=> 'required',
            'user_id'=> 'required',
            'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:10240',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $millage = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->first();

            if($millage->id >0){
                if($request->hasFile('document')) {
                    putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                    $storage = new StorageClient();
                    $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                    if($millage->document !=''){
                        $object = $bucket->object('millage_images/'.$millage->document);
                        $object->delete();
                    }
                    $filename = $request->file('document')->getClientOriginalName(); // get the file name
                    $getfilenamewitoutext = pathinfo($filename, PATHINFO_FILENAME); // get the file name without extension
                    $getfileExtension = $request->file('document')->getClientOriginalExtension(); // get the file extension
                    $createnewFileName = time().'_'.str_replace(' ','_', $getfilenamewitoutext).'.'.$getfileExtension; // create new random file name
                    // $request->document->move(config('app.millage_img_path'), $createnewFileName);
                    // $filePath = public_path('millage_images').'/'.$createnewFileName;
                    // $objectName = $createnewFileName;
                    // $path = $bucket->upload(
                    //     fopen($filePath, 'r'), // Open the file in read mode
                    //     [
                    //         'name' => 'millage_images/'.$objectName // Set the file name in the bucket
                    //     ]
                    // );
                    // $object = $bucket->object('millage_images/'.$objectName);
                    // $object->update([
                    //     'acl' => [
                    //         ['entity' => 'allUsers', 'role' => 'READER']
                    //     ]
                    // ]);
                    // $image_path = config('app.millage_img_path')."/{$createnewFileName}";
                    // if (File::exists($image_path)) {
                    //     unlink($image_path);
                    // }
                    $image = $request->file('document');
                    $tempPath = $image->store('temp', 'public'); // temporarily store
                    $input['document'] = $createnewFileName;
                    ProcessMillageImageUpload::dispatch($tempPath, $createnewFileName);
                }
                if(!isset($input['personal_millage']))
                $input['personal_millage'] = 0;

                $millageDate = explode("-",$input['millage_date']);
                $input['millage_date'] = $millageDate[2].'-'.$millageDate[1].'-'.$millageDate[0];
                $millage = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Millage edited successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }
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
            $millage = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($millage->id >0){
                if($millage->document !=''){
                    putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                    $storage = new StorageClient();
                    $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                    if($millage->document !=''){
                        $object = $bucket->object('millage_images/'.$millage->document);
                        $object->delete();
                    }
                }
                $Millage = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->delete();
                return ['response'=>true, 'msg'=>'Millage deleted successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

    public function removeMillageImage(Request $request){
        $rules = array(
            'id'=>'required',
            'user_id'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $millage = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($millage->id >0){
                    if($millage->document !=''){
                        putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                        $storage = new StorageClient();
                        $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                        if($millage->document !=''){
                            $object = $bucket->object('millage_images/'.$millage->document);
                            $object->delete();
                        }
                    }
                $input['document'] = null;
                $Transaction = Millage::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
                return ['response'=>true, 'msg'=>'Millage image removed successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }


}
