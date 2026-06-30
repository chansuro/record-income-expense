@extends('layouts.signup')
@section('content')

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <!-- Email Address -->
        <div class="card">
            <h2>TaxiTax Login</h2>
           <x-input-error :messages="$errors->get('email')" class="mt-2 alert alert-danger" style="list-style: none;" />
            <input type="email" placeholder="Email address" name="email" value="{{ old('email') }}">
            
            <button class="btn btn-primary ms-auto me-4" style="line-height: 17px;">Send OTP</button>
        </div>
        
            
    </form>
@endsection
