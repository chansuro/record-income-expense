<!DOCTYPE html>
<html style="font-size: 16px;" lang="en">

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta charset="utf-8">
  <meta name="keywords" content="INTUITIVE">
  <meta name="description"
    content="Fully app-based accounting and book-keeping services for Taxi and Delivery Drivers.">
  <title>Contact Us | TaxiTax.uk</title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- <link rel="stylesheet" href="/homepage/css/nicepage.css" media="screen"> -->
   
  <link rel="stylesheet" href="/homepage/css/index.css" media="screen">
  <link rel="stylesheet" href="/homepage/css/privacy.css" media="screen"> 
  <!-- New Added End -->
  <script class="u-script" type="text/javascript" src="/homepage/js/jquery.js" defer=""></script>
  <script class="u-script" type="text/javascript" src="/homepage/js/nicepage.js" defer=""></script>
  <meta name="generator" content="Nicepage 7.9.4, nicepage.com">



  <link id="u-theme-google-font" rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i|Open+Sans:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i">
  <script type="application/ld+json">{
		"@context": "http://schema.org",
		"@type": "Organization",
		"name": "taxitax",
		"logo": "/homepage/images/finalappIconRounded.png"
}</script>
<script src="https://www.google.com/recaptcha/api.js?render={{ config('app.google_captcha_site_key') }}"></script>
  <meta name="theme-color" content="#478ac9">
  <meta property="og:title" content="Privacy | TaxiTax.uk">
  <meta property="og:description"
    content="Fully app-based accounting and book-keeping services for Taxi and Delivery Drivers.">
  <meta property="og:type" content="website">
  <meta data-intl-tel-input-cdn-path="intlTelInput/">
 <script>
grecaptcha.ready(function() {
    grecaptcha.execute('{{ config('app.google_captcha_site_key') }}', {action: 'submit'}).then(function(token) {
        document.getElementById('recaptcha_token').value = token;
    });
});
</script>
</head>

<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="en">
    <header class="main-header" id="header">
    <div class="container">
      <div class="header-qrap">
        <!-- <a href="/" class="main-logo" data-image-width="552" data-image-height="552">
          <img src="/homepage/images/finalappIconRounded.png" class="u-logo-image u-logo-image-1">
        </a> --> 
        <nav class="navbar header-flex"> 
            <a href="/" class="main-logo" data-image-width="552" data-image-height="552">
              <img src="/homepage/images/finalappIconRounded.png" class="u-logo-image u-logo-image-1">
            </a> 
            <a href="https://taxitax.uk/signup" class="btn btn-primary ms-auto me-4">Sign Up</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
              data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
              aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li><a href="/">Home</a></li>
                <li><a href="https://taxitax.uk/pricing">Pricing</a></li>
                <li><a href="https://taxitax.uk/aboutus">About Us</a></li>
                 <li><a href="https://taxitax.uk/faq">FAQs</a></li>
                <li><a class="active" href="https://taxitax.uk/contactus">Contact Us</a></li>
              </ul> 
            </div> 
        </nav> 
      </div>
    </div>
  </header>
  <div class="common-banner">
    <img src="/homepage/images/contact-banner.jpg" alt="Banner" class="inner-img">
    <div class="innertext">
      <div class="container">
        <div class="innertext-box text-white">
          <h1>Contact Us</h1>
        </div>
      </div>
    </div>
  </div>
  <section class="cmn-gap contact-page">
    <div class="container">
      <div class="row gy-5 justify-content-center">
        <!-- <div class="col-md-6">
          <div class="shadowbox">
            <h2 class="text-center mb-2">Get In Touch</h2>
            <div class="coninfo">
              <ul>
                <li><img src="/homepage/images/location.png" alt="Banner" class="cicon"> Lorem Ipsum Doler
                  <br>
                  Kolkata <br>
                  Newtown <br> 
                  Pin - 700102
                </li>
                <li><img src="/homepage/images/phone.png" alt="Banner" class="cicon"><a href="tel:1234567890">1234567890,</a> <a href="tel:1234567890">1234567890</a>
                  </a></li>
                <li><img src="/homepage/images/mail.png" alt="Banner" class="cicon"><a
                    href="mailto:test123@gmail.com">test123@gmail.com</a></li>
              </ul>
            </div>
          </div>
        </div> -->
        <div class="col-md-6">
          <div class="shadowbox">
            <h2 class="heading">Send Us A Message </h2>
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <div class="con-form">
              <div class="row">
                <form  action="{{ route('general.sendcontactinfo' ) }}" method="post">
                  @csrf
                  <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                <div class="col-sm-12">
                  <div class="labelWrap">
                    <input type="text" placeholder="Your Name" name="name">
                  </div>
                </div>
                <div class="col-sm-12">
                  <div class="labelWrap">
                    <input type="text" placeholder="Your Email" name="email">
                  </div>
                </div>
                <div class="col-sm-12">
                  <div class="labelWrap">
                    <input type="text" placeholder="Contact Number" name="phone">
                  </div>
                </div>
                <div class="col-sm-12">
                  <div class="labelWrap">
                    <textarea id="" placeholder="Message" name="message"></textarea>
                  </div>
                </div>
                <div class="col-sm-12">
                  <button type="submit" class="btn">Submit</button>
                </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- <div class="map">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3684.2775523970677!2d88.4510101750776!3d22.56872027949447!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a027500155becc5%3A0xe63ea5455c671304!2sPolenite%20Mission%20Para!5e0!3m2!1sen!2sin!4v1762372812771!5m2!1sen!2sin" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div> -->



<footer class="main-footer" id="footer">
    <div class="container">
      <!-- <a href="/" class="f-logo" data-image-width="552" data-image-height="552">
        <img src="/homepage/images/finalappIconRounded.png" class="u-logo-image u-logo-image-1">
      </a> -->
      <nav class="fnav">
        <ul>
          <li><a href="/">Home</a></li>
          <li><a href="https://taxitax.uk/pricing">Pricing</a></li>
          <li><a href="https://taxitax.uk/aboutus">About Us</a></li>
           <li><a href="https://taxitax.uk/faq">FAQs</a></li>
          <li><a class="active" href="https://taxitax.uk/contactus">Contact Us</a></li>
          <li><a href="https://taxitax.uk/privacy">Privacy Policy</a></li>
          <li><a href="https://taxitax.uk/terms">Terms and Conditions</a></li>
        </ul>
      </nav>
      <div class="copyright text-center">
        <p>TaxiTax.uk is managed and operated by Apptax Ltd.</p>
      </div>
    </div>
  </footer> 
  <section class="u-backlink u-clearfix u-grey-80"></section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin="anonymous"></script>
</body>

</html>