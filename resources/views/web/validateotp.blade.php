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
<div class="verfctn-cntnr">
    <form method="POST" action="{{ route('general.postvalidateotp') }}">
                    @csrf
<h2>Verification</h2>
<div id="otpMessage" class="mt-3"></div>
                <p class="mb-1">We have sent code to your number</p>
                <div class="number">+44 {{$phone}}</div>
                <div class="d-flex mb-5">
                    <div class="radio-otp">
                        <input type="number" name="otp1" maxlength="1"  id="otp1" required class="otp-input">
                    </div>
                    <div class="radio-otp">
                        <input type="number" name="otp2" maxlength="1"  id="otp2" required class="otp-input">
                    </div>
                    <div class="radio-otp">
                        <input type="number" name="otp3" maxlength="1"  id="otp3" required class="otp-input">
                    </div>
                    <div class="radio-otp">
                        <input type="number" name="otp4" maxlength="1" required id="otp4" class="otp-input">
                    </div>
                </div> 
                 <div class="bottom-text text-start">
                    Didn't receive link? <a href="" onclick="resendOtp(event)">Resend</a>
                </div>
                <button class="signup-btn">Continue</button> 
                <script>
                function resendOtp(event) {
                    event.preventDefault(); 
                    const messageBox = document.getElementById('otpMessage');
                    messageBox.innerHTML = '';
                    fetch('/resend-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ 
                            phone: '{{ $phone }}'
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.message) {
                            messageBox.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        } else {
                            messageBox.innerHTML = `<div class="alert alert-warning">Something went wrong</div>`;
                        }
                    })
                    .catch(err => console.error(err));
                }

                document.querySelectorAll('.otp-input').forEach((input, index, inputs) => {
                input.addEventListener('input', (e) => {
                    const value = e.target.value;
                     // allow only 1 digit
                    e.target.value = value.replace(/[^0-9]/g, '').slice(0, 1);
                    if (value.length === 1 && index < inputs.length - 1) {
                        inputs[index + 1].focus(); // move to next
                    }
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });
</script>
</form>
</div>
@endsection