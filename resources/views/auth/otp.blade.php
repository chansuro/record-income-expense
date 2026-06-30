@extends('layouts.signup')
@section('content')

    <form method="POST" action="{{ route('emailotp') }}">
        @csrf
        <!-- Email Address -->
        <div class="card">
            <h2>Verify your OTP</h2>
            <x-input-error :messages="$errors->get('otp')" class="mt-2 alert alert-danger" style="list-style: none;" />
            <input type="password" placeholder="Enter 6-digit OTP" name="otp">
            
            <button class="btn btn-primary ms-auto me-4" style="line-height: 17px;">Verify & Login</button>
        </div>
        
            
    </form>
@endsection
