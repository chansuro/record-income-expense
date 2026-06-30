@extends('layouts.signup')
@section('content')
        <!-- Email Address -->
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

            <!-- BILLING -->

                <h2>Billing</h2>
                <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Bill Date</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Invoice</th>
                        <th>Bill Date</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($billing as $item)
                    <tr>
                        <td>{{ $item->invoice_id }}</td>
                        <td>{{ date('F Y', $item->invoice_date) }}</td>
                        <td>£{{ $item->amount/100 }}</td>
                        <td> <a href="{{ $item->invoice_link }}" target="_blank"><i class="fas fa-download fa-sm text-info"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

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
                <a class="logout-btn" href="{{ route('user.dashboard') }}">Home
                </a>
                <a class="logout-btn" href="{{ route('user.logout') }}">
                    Logout
                </a>

            </div>
@endsection