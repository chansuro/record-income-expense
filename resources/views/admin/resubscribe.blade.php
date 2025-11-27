@extends('layouts.signup')
@section('content')
<div class="reactve-subs">
    <div class="text-center">
        <div class="reactve-subs-icon-wrap"><img class="reactve-subs-icon" src="" alt=""></div>
    <div class="reactve-subs-title">Your subscription has expired</div>
    <p>We couldn't renew your subscription. Your access to Taxitax  App is paused - reactive to continue where you left off.</p>
    </div>
    <div class="crnt-plan">
        <div class="d-flex mb-1">
            <div class="crnt-plan-title">Current plan</div>
            <div class="crnt-plan-price">£{{ $subscription_price }}</div>
        </div>
        <div class="d-flex mb-1">
            <div class="crnt-plan-title">Name</div>
            <div class="crnt-plan-price">{{ $name }}</div>
        </div>
        <div class="d-flex mb-1">
            <div class="crnt-plan-title">Email</div>
            <div class="crnt-plan-price">{{ $email }}</div>
        </div>
        <div class="d-flex mb-1">
            <div class="crnt-plan-title">Phone</div>
            <div class="crnt-plan-price">+44{{ $phone }}</div>
        </div>
        <p class="mb-0">No data was lost - you can reactive any time.We keep your settings and history safe</p>
    </div>
    <div class="payment-wrap">
        
        <form id="payment-form" class="row g-0" style="display:none">
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
            <div class="col-12 mb-3"><button type="submit" id="submit" class="w-full py-3 signup-btn">Continue</button></div>
        </form>
    

    <button type="submit" class="w-full py-3 signup-btn mb-4 mt-3" onclick="javascript: showForm(this)">Reactive subscription</button> 
        <div class="text-center mb-4"><a class="contact-support" href="/support">Contact support</a></div>
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
    fetch('/admin/resubscribepost', {
        method: "POST",
        headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify(
            {
                token: token.id,
                payment_method: paymentMethod.id, 
                plan: '{{ config('services.stripe.price') }}',
            }
        )
    }).then(response => {
        console.log(response)
        if (response.ok) {
        alert('Subscription successful!');
        } else {
        alert('Subscription failed.');
        }
    });
    }
});
function showForm(element){
    const frm = document.getElementById("payment-form");
    frm.style.display = "block";
    element.style.display = 'none';
}
</script>
@endsection