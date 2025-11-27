<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up with Email</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- ✅ Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/font-awesome.min.css">


    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body{font-family: 'Poppins', sans-serif;}
        .signup-body {
            background-color: #f4f8fb;
            min-height: 100vh;
            margin: 0;
            padding: 30px;
        }

        .signup-container {
            background-color: #f2f6f9;
            padding: 30px 25px;
            border-radius: 20px;
            overflow: hidden;
            width: 100%;
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .signup-container a {
            color: #163fff;
        }

        .signup-container h2 {
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 5px;
            color: #111;
        }

        .signup-container p {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .signup-container label {
            display: block;
            font-weight: 500;
            color: #222;
            margin-bottom: 6px;
            margin-top: 15px;
            font-size: 15px;
        }

        .signup-container input {
            width: 100%;
            padding: 12px 15px;
            height: 50px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            color: #222;
            transition: border 0.3s ease;
        }

        .signup-container input:focus {
            border-color: #00b6e6;
        }

        .signup-container input::placeholder {
            color: #222;
        }

        .signup-container .phone-input {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 25px;
            overflow: hidden;
            background: #fff;
        }

        .signup-container [type="checkbox"] {
            width: 20px;
            height: 20px;
            padding: 0;
            margin: 0;
        }

        .signup-container label[for="agree"] {
            margin: 0;
            font-size: 12px;
            color: #a3a3a3;
        }

        .signup-container label[for="agree"] a {
            text-decoration: none;
        }

        .signup-container .phone-input img {
            width: 26px;
            height: 18px;
            margin-left: 12px;
        }

        .signup-container .phone-input input {
            border: none;
            flex: 1;
            padding-left: 10px;
        }

        .signup-container .referral-label {
            color: #00b6e6;
        }

        .signup-container .terms {
            margin-top: 15px;
            font-size: 13px;
            color: #777;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .signup-container .signup-container .terms a {
            color: #00b6e6;
            text-decoration: none;
        }

        .signup-container .signup-btn {
            background-color: #00b6e6;
            color: #fff;
            font-weight: 500;
            border: none;
            width: 100%;
            padding: 10px 14px;
            border-radius: 25px;
            font-size: 16px;
            margin-top: 25px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .signup-container .signup-btn:hover {
            background-color: #009bb5;
        }

        .signup-container .divider {
            text-align: center;
            margin: 20px 0;
            color: #222;
            font-weight: 400;
        }

        .signup-container .bottom-text {
            text-align: center;
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }

        .signup-container .bottom-text.sml {
            font-size: 12px;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .signup-container .bottom-text a {
            color: #163fff;
            font-weight: 500;
            text-decoration: none;
        }

        .signup-container .footer-links {
            text-align: center;
            font-size: 14px;
            color: #163fff;
        }

        .signup-container .footer-links a {
            padding: 0 10px;
            text-decoration: none;
            color: #163fff;
            border-right: 1px solid #ccc;
            font-weight: 500;
        }

        .signup-container .footer-links a:last-child {
            border: none;
        }

        .form-group {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 45px;
        }

        .payment-wrap p{
            color: #909090;
            font-size: 14px;
        }
        .pymnt-title-top{
           background-color: #0039a4;
            color: #fff;
            padding: 15px;
            text-align: center;
            font-size: 24px;
            font-weight: 400;
            margin: -30px -30px 20px;
        }
        .pymnt-title{ 
            color: #0039a4; 
            font-weight: 700;
            font-size: 28px;
        }
        .pymnt-sml-title{
              color: #0039a4; 
            font-weight: 500;
            font-size: 17px;
            margin-bottom: 5px;
        }
        .pymnt-sml-title2{
            color: #0039a4;
            font-weight: 500;
            font-size: 20px;
            margin-bottom: 10px;
            margin-top: 30px;
        }
        .signup-container .payment-wrap label{
          display: none;
        }
       .signup-body{padding: 0;}
        .card-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .crnt-plan {
            padding: 15px;
            border: 1px solid #6a6a6a;
            border-radius: 20px;
        }
        .crnt-plan-price {
            font-weight: 500;
            margin-left: auto;
        }
        .crnt-plan-title {
            color: #626262;
        }
        .signup-container a.contact-support{color: #5d5d5d;}
       
.reactve-subs-title {
    font-size: 20px;
    margin-bottom: 10px;
    font-weight: 500;
}
        @media (max-width: 480px) {
            .signup-container .signup-container {
                padding: 25px 20px;
                border-radius: 15px;
            }

            .signup-container label[for="agree"] {
                max-width: 260px;
            }
            .signup-container{border-radius: 0;}
        }
         /* New css */
        .radio-otp input{
            width: 50px;
            height: 50px;
            border-radius: 100%;
            border: 2px solid #cacaca;
            background-color: #ececec;
            text-align: center;
            padding: 0;
        }
        .radio-otp{
            margin: 0 14px 0 0;
        }
        .number{margin-bottom: 20px; font-weight: 500;}
        .verfctn-cntnr{
            display: flex; flex-direction: column;
            min-height: 100vh;
        }
        .verfctn-cntnr .signup-btn{
            margin-top: auto;
        }
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Remove number input arrows (Firefox) */
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>

<body class="signup-body">
    <div class="row justify-content-center">
        <div class="col-md-5 col-sm-5">
            <div class="signup-container">
                @yield('content')

                <div class="bottom-text sml">
                    By using our services you are agreeing to our <a href="/terms">Terms</a> and <a href="/privacy">Privacy
                        Policy</a>
                </div>

                <div class="footer-links">
                    <a href="/aboutus">About Us</a>
                    <a href="/contactus">Contact Us</a>
                    <a href="/faq">FAQ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        jQuery(".toggle-password").click(function () {

            jQuery(this).toggleClass("fa-eye fa-eye-slash");
            var input = jQuery(jQuery(this).attr("toggle"));
            if (input.attr("type") == "password") {
                input.attr("type", "text");
            } else {
                input.attr("type", "password");
            }
        });
    </script>
</body>

</html>