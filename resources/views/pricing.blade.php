<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TaxiTax � Simple Tax for UK Taxi Drivers</title>
  <style>
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #f8fafc;
      color: #0f172a;
    }
    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 40px 20px;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 80px;
    }
    .logo {
      font-weight: 700;
      font-size: 1.2rem;
    }
    .hero {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: center;
    }
    .hero h1 {
      font-size: 2.8rem;
      line-height: 1.2;
      margin-bottom: 20px;
    }
    .hero p {
      font-size: 1.1rem;
      color: #475569;
      margin-bottom: 30px;
    }
    .cta-btn {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 14px 28px;
      font-size: 1rem;
      border-radius: 8px;
      cursor: pointer;
    }
    .card {
      background: #ffffff;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      text-align: center;
    }
    .price {
      font-size: 3rem;
      font-weight: 700;
      margin: 20px 0;
    }
    .price span {
      font-size: 1rem;
      color: #64748b;
    }
    ul {
      list-style: none;
      padding: 0;
      margin: 30px 0;
      text-align: left;
    }
    ul li {
      margin-bottom: 12px;
    }
    footer {
      text-align: center;
      color: #64748b;
      margin-top: 100px;
      font-size: 0.9rem;
    }
    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">TaxiTax</div>
      <button class="cta-btn">Get Started</button>
    </header>

    <section class="hero">
      <div>
        <h1>Stress-free accounting & tax for UK taxi & delivery drivers</h1>
        <p>
          Track income, expenses, and mileage, then submit your Self Assessment � all from one simple app.
        </p>
        <button class="cta-btn">Start Free Trial</button>
      </div>

      <div class="card">
        <h2>One Simple Plan</h2>
        <div class="price">�6.95 <span>/ month</span></div>
        <div style="margin-top:8px; color:#475569; font-size:0.95rem;">Less than <strong>25p a day</strong> � Cancel anytime</div>
        <ul>
          <li>? Built for UK taxi & private hire drivers</li>
          <li>? Income, expenses & mileage tracking</li>
          <li>? HMRC-ready reports & summaries</li>
          <li>? Monthly account summaries</li> 
          <li>? Making Tax Digital support</li>
          <li>? Android and iOS App</li>
          <li>? Secure cloud access</li>
          <li>? Email support</li>
        </ul>
        <!--
        Optional future enhancement:
        <div style="margin-top:20px; font-size:0.95rem; color:#475569;">
          Or save with <strong>�69 / year + VAT</strong>
        </div>
        -->
        <button class="cta-btn">Subscribe Now</button>
      </div>
    </section>

    <footer>
      � 2025 TaxiTax.uk. All rights reserved.
    </footer>
  </div>
</body>
</html>
