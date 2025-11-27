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
<script src="https://cdn.tailwindcss.com"></script>
<div class="payment-wrap">
    <div class="pymnt-title-top">Payment</div> 
    <div class="pymnt-title text-center">$5.95</div>
    <div class="pymnt-short-text">
        <p class="text-center">Per month - no contract</p>
        <div class="pymnt-sml-title text-center">Your 3 days Free trial will start today!</div>
        <p class="text-center">Cancel your subscription at any timme during the <br> 3 days free period</p>
        <p class="text-center">You will be billed on 05-11-25 and then on the <br> 05-11-2025 of each month</p>
        <p class="text-center">You won't be charged now. Payment only taken after your free 3 days</p>
        <div class="pymnt-sml-title2">Add your payment information</div>
        <p>Card information</p>
    </div>
    <form id="payment-form" class="row g-0">
      <div class="col-12">
          <div class="position-relative">
        <label>Card Number</label>
        <div id="card-number-element" class="p-3 pt-4 border bg-gray-50"></div>
        <div class="card-icon"></div>
        </div>
      </div>
    
      <div class="col-6">
        <label>Expiry Date</label>
        <div id="card-expiry-element" class="p-3 pt-4 border bg-gray-50"></div> 
      </div>
    
      <div class="col-6">
          <div class="position-relative">
        <label>CVC</label>
        <div id="card-cvc-element" class="p-3 pt-4 border bg-gray-50"></div>
        <div class="card-icon"></div>
        </div>
      </div> 
    <div class="col-12 mb-3"><button type="submit" id="submit" class="w-full py-3 signup-btn">Continue</button>
    </form>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe("{{ config('services.stripe.publishable_key') }}");
const elements = stripe.elements();
const style = {
  base: {
    color: "#32325d",
    fontFamily: 'Arial, sans-serif',
    fontSmoothing: "antialiased",
    fontSize: "16px",
    "::placeholder": { color: "#a0aec0" }
  },
  invalid: {
    color: "#fa755a",
    iconColor: "#fa755a"
  }
};

const cardNumberElement = elements.create("cardNumber", { style });
cardNumberElement.mount("#card-number-element");
const cardExpiryElement = elements.create("cardExpiry", { style });
cardExpiryElement.mount("#card-expiry-element");

const cardCvcElement = elements.create("cardCvc", { style });
cardCvcElement.mount("#card-cvc-element");

//const cardElement = elements.create('card');
//cardElement.mount('#card-element');
const form = document.getElementById('payment-form');
const cardHolderName = 'Kolkata Surajit';
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const {token, error} = await stripe.createToken(cardNumberElement);
    const { paymentMethod, paymenterror } = await stripe.createPaymentMethod({
        type: 'card',
        card: cardNumberElement,
        billing_details: { name: cardHolderName }
    });
    if (error || paymenterror) {
        console.error(error);
    } else {
    // Send the token to your server
    fetch('/subscribe', {
        method: "POST",
        headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify(
            {
                token: token.id,
                payment_method: paymentMethod.id, 
                plan: '{{ config('services.stripe.price') }}'
            }
        )
    }).then(response => {
        if (response.ok) {
        alert('Subscription successful!');
        } else {
        alert('Subscription failed.');
        }
    });
    }
});
</script>
@endsection