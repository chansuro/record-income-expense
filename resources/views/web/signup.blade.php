@extends('layouts.signup')
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>
                    @if ($error === 'NOREFCODE')
                        No valid referral code was found. Would you like to continue without one?<br><button
                            type="button"
                            class="btn btn-primary btn-sm ms-2"
                            id="continueWithoutReferral"
                        >
                            Continue without referral
                        </button>
                    @else
                    {{ $error }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
<form method="POST" id="signupForm" action="{{ route('general.signuppost') }}">
                    @csrf
<h2>Create your account online, then download the app and log in OR You can also sign up on the app.</h2>
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
                            <input type="tel" placeholder="Your Phone Number" name="phone" value="{{ old('phone') }}" maxlength="10" 
    pattern="[0-9]{10}" >
                            
                        </div><small>Enter your phone number without '0' at the front</small>

                    </div>
                    <div class="col-lg-12">
                        <div class="form-group">
                            <label>Password</label>
                            <input id="password-field" type="password" class="form-control" name="password"
                                value="secret" placeholder="Type your password">
                            <span toggle="#password-field" class="fa fa-eye-slash field-icon toggle-password"></span>

                        </div>
                        <small>Minimum 8 characters</small>
                    </div>
                    <!-- <label>Password</label>
                    <input type="password" placeholder="Type your password"> -->
                    <div class="col-lg-12">
                        <label class="referral-label">Referral Code (if any)</label>
                        <input type="text" placeholder="Type referral code" name="referral_code" value="{{ old('referral_code') }}">
                    </div>
                </div>
                <div class="terms">
                    <input type="checkbox" id="agree" name="agree" value="1" checked="@if (old('agree')) checked @endif" required>
                    <label for="agree">By Creating your account you have to agree with our <a href="#">Terms and
                            Condition</a></label>
                </div>

                <button class="signup-btn">Sign Up Now</button>
                </form>
                <script>
                    document.getElementById('continueWithoutReferral').addEventListener('click', function() {
                        // Clear the referral code input field
                        document.querySelector('input[name="referral_code"]').value = '';
                        // Submit the form
                        //this.closest('form').submit();
                        document.getElementById('signupForm').submit();
                    });

                </script>
@endsection