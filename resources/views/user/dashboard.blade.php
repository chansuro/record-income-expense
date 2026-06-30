@extends('layouts.signup')
@section('content')
<style>

/* Card */
.card{
    background:#fff;
    border-radius:16px;
    padding:28px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
    margin-bottom:22px;
}

/* Headings */
h2{
    margin-bottom:16px;
}

/* Inputs */
input{
    width:100%;
    padding:14px;
    border:1px solid #ddd;
    border-radius:12px;
    margin-bottom:12px;
    font-size:15px;
}

/* Primary Button */
.btn{
    width:100%;
    padding:14px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:12px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.btn:hover{
    background:#1d4ed8;
}

/* Profile */
.profile{
    text-align:center;
}

.avatar{
    width:80px;
    height:80px;
    border-radius:50%;
    overflow:hidden;
    background:#2563eb;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    font-weight:bold;
    margin:0 auto 10px;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
    display:block;
}

.email{
    color:#666;
    font-size:14px;
}

/* Rows */
.row{
    display:flex;
    justify-content:space-between;
    padding:12px 0;
    border-bottom:1px solid #eee;
    font-size:14px;
}

/* Billing */
.plan{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    padding:16px;
    border-radius:12px;
    margin-bottom:15px;
}

.price{
    font-size:22px;
    font-weight:bold;
    color:#2563eb;
    margin-top:6px;
}

/* Logout */
.logout-btn{
    width:100%;
    padding:14px;
    background:#fff;
    color:#ef4444;
    border:1px solid #fecaca;
    border-radius:12px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.logout-btn:hover{
    background:#fef2f2;
    border-color:#ef4444;
}

/* App Download Buttons */
.downloads{
    display:flex;
    gap:12px;
    margin-top:10px;
}

.store-btn{
    flex:1;
    padding:14px;
    border-radius:12px;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size:14px;
    color:white;
    transition:.2s;
}

.android{
    background:#22c55e;
}

.android:hover{
    background:#16a34a;
}

.ios{
    background:#111;
}

.ios:hover{
    background:#000;
}
</style>
    <form method="POST" action="{{ route('emailotp') }}">
        @csrf
        <!-- Email Address -->
        <div class="card">
            <!-- PROFILE -->
            <div class="card">

                <div class="profile">
                    <div class="avatar">
                        @if ($user->avatar)
                            <img src="https://storage.googleapis.com/taxitax/avatar_images/{{$user->avatar }}" alt="">
                            @else
                            @php
                            $parts = explode(' ', trim($user->name));

                            $firstInitial = strtoupper(substr($parts[0], 0, 1));

                            $lastInitial = count($parts) > 1
                                ? strtoupper(substr(end($parts), 0, 1))
                                : '';
                            @endphp

                            {{ $firstInitial }} {{ $lastInitial }}
                        @endif
                    </div>
                    <h2>{{$user->name}}</h2>
                    <div class="email">{{$user->email}}</div>
                </div>

                <div class="row">
                    <span>Member Since</span>
                    <strong>{{$user->created_at->format('Y')}}</strong>
                </div>

                <div class="row">
                    <span>Status</span>
                    
                        @if ($user->status == 4)
                         <strong style="color:#10b981;">Re-subscrive</strong>   <a class="btn btn-primary ms-auto me-4" style="line-height: 17px;" href="{{route('user.subscribeuser')}}">Re-subscrive</a>
                        @elseif ($user->status == 1)
                        <strong style="color:#10b981;">Active</strong>   
                        @elseif ($user->status == 3)
                        <strong style="color:#10b981;">Admin Suspended</strong>   
                        @elseif ($user->status == 5)
                        <strong style="color:#10b981;">User Suspended</strong>
                         
                        @endif
                    
                </div>

                <div class="row">
                    <span>Plan</span>
                    <strong>Premium</strong>
                </div>

            </div>
            @if ($billing)
              <!-- BILLING -->
            <div class="card">

                <h2>Billing</h2>

                <div class="plan">
                    <div><strong>TaxiTax Premium</strong></div>
                    <div class="price">£6.99 / month</div>
                    <div style="font-size:13px;color:#666;margin-top:6px;">
                        Tax tracking, mileage logs & reports
                    </div>
                </div>

                <div class="row">
                    <span>Next Payment </span>
                    <strong>{{ date('d F Y', $billing->subscription_to) }}</strong>
                </div>

                

                <br>

                <a class="btn btn-primary ms-auto me-4" style="line-height: 17px;" href="{{route('user.subscriptions')}}">Manage Subscription</a>

            </div>  
            @endif
            

            <!-- APP DOWNLOAD -->
            <div class="card">

                <h2>Get the TaxiTax App</h2>

                <p style="color:#666;font-size:14px;margin-bottom:12px;">
                    Download the mobile app for faster access on the go.
                </p>

                <div class="downloads">

                    <a href="https://play.google.com/store/apps/details?id=com.taxitax.apptax" target="_blank"><img class="u-image u-image-contain u-image-default u-preserve-proportions u-image-3 str-img" src="/homepage/images/playstore.png" alt="" data-image-width="168" data-image-height="50"></a>

                    <a href="https://apps.apple.com/gb/app/taxitax-accounting-taxation/id6752342488" target="_blank"><img class="u-image u-image-contain u-image-default u-preserve-proportions u-image-2 str-img" src="/homepage/images/app.png" alt="" data-image-width="167" data-image-height="50"></a>

                </div>

            </div>

            <!-- LOGOUT -->
            <div class="card">

                <h2>Account</h2>

                <a class="logout-btn" href="{{ route('user.logout') }}">
                    Logout
                </a>

            </div>
        </div>
        
            
    </form>
@endsection