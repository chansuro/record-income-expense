@extends('layouts.signup')
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<form method="POST" action="{{ route('general.signuppost') }}">
                    @csrf
<h2>Sign up with Email</h2>
                <p>Please enter your details!</p>
                <div class="row">
                    <div class="col-lg-6">
                        <label>Name</label>
                        <input type="text" placeholder="Type your name" name="name" value="{{ old('name') }}">
                    </div>
                    <div class="col-lg-6">
                        <label>Email</label>
                        <input type="email" placeholder="Type your email" name="email" value="{{ old('email') }}">
                    </div>

                    <div class="col-lg-12">



                        <label>Phone Number</label>
                        <div class="phone-input">
                            <img src="{{ asset('signup_assets/images/gb.png') }}" alt="UK Flag">
                            <input type="tel" placeholder="Your Phone Number" name="phone" value="{{ old('phone') }}">
                        </div>

                    </div>
                    <div class="col-lg-12">
                        <div class="form-group">
                            <label>Password</label>
                            <input id="password-field" type="password" class="form-control" name="password"
                                value="secret" placeholder="Type your password">
                            <span toggle="#password-field" class="fa fa-eye-slash field-icon toggle-password"></span>

                        </div>
                    </div>
                    <!-- <label>Password</label>
                    <input type="password" placeholder="Type your password"> -->
                    <div class="col-lg-12">
                        <label class="referral-label">Referral Code (if any)</label>
                        <input type="text" placeholder="Type referral code" name="referral_code" value="{{ old('referral_code') }}">
                    </div>
                </div>
                <div class="terms">
                    <input type="checkbox" id="agree" name="agree" value="1" required>
                    <label for="agree">By Creating your account you have to agree with our <a href="#">Terms and
                            Condition</a></label>
                </div>

                <button class="signup-btn">Sign Up Now</button>
                </form>
@endsection