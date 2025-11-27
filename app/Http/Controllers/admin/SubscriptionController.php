<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Plan;
use Stripe\Webhook;
use Stripe\Invoice;
use Stripe\Subscription;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Checkout\Session as StripeSession;


class SubscriptionController extends Controller
{
    //
    public function index(Request $request){
        $input = $request->all();
        if(isset($request->u)){
            $user = User::where('id',$request->u)->first();
        }
        $query = Billing::where('user_id',$input['u']);
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $FromDateArr = explode("-",$request->start_date);
            $FromDate = $FromDateArr[2].'-'.$FromDateArr[1].'-'.$FromDateArr[0].' 00:00:00';

            $ToDateArr = explode("-",$request->end_date);
            $ToDate = $ToDateArr[2].'-'.$ToDateArr[1].'-'.$ToDateArr[0].' 23:59:59';
            $query->whereBetween('billing.invoice_date', [strtotime($FromDate), strtotime($ToDate)]);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $FromDateArr = explode("-",$request->start_date);
            $FromDate = $FromDateArr[2].'-'.$FromDateArr[1].'-'.$FromDateArr[0].' 00:00:00';
            $query->where('billing.invoice_date', '>=', strtotime($FromDate));
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $ToDateArr = explode("-",$request->end_date);
            $ToDate = $ToDateArr[2].'-'.$ToDateArr[1].'-'.$ToDateArr[0].' 23:59:59';
            $query->where('billing.invoice_date', '<=', strtotime($FromDate));
        });
        
        $Subscription = $query->paginate(20);
        return view('admin.subscriptions',['subscription'=>$Subscription,'user'=>(isset($user))?$user: null] );
    }

    public function resubscribe(){
        $data['subscription_price'] = config('services.subscription.price');
        $user = Auth::guard('web')->user();
        $data['name'] = $user->name;
        $data['email'] = $user->email;
        $data['phone'] = $user->phone;
        return \view('admin.resubscribe',$data);
    }

    public function resubscribepost(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to resubscribe.');
        }
        //try{
            Stripe::setApiKey(config('services.stripe.secret'));
            if(!$user->stripe_customer){
                $customer = Customer::create([
                    'email' => $user->email,
                    'source'  => $request->token,
                ]);
                $customerId = $customer->id;
            }else{
                $customerId = $user->stripe_customer;
            }
            $paymentMethod = PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $customerId]);
            $subscription = Subscription::create([
                    'customer' => $customerId,
                    'items' => [
                        ['plan' => config('services.stripe.price')], // Plan ID from your Stripe account
                    ],
                    'expand' => ['latest_invoice.payment_intent']
            ]);
            $updateinput['status'] = 1;
            $updateinput['subscription_id'] = $subscription->id;
            $updateinput['stripe_customer'] = $customerId;
            User::where('id',$user->id)->update($updateinput);
            return response()->json([
                'success' => true,
                'data'=>$user
            ]);
            //return redirect()->back()->with('success', 'Resubscription record created. Please complete payment to activate.');
        // } catch (\Exception $e) {
        //     return redirect()->back()->with('error', 'Failed to create resubscription. Please try again.');
        // }
    }
}
