<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Income & Expense Statement</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      padding: 30px;
    }
    .statement {
      max-width: 500px;
      margin: auto;
      background-color: #ffffff;
      border: 1px solid #ddd;
      padding: 20px;
    }
    .header {
      text-align: center;
      background-color: #cdeaf3;
      padding: 15px;
      margin-bottom: 20px;
    }
    .header h2, .header p {
      margin: 5px 0;
    }
    .section {
      margin-bottom: 20px;
    }
    .section h3 {
      background-color: #406c9e;
      color: white;
      padding: 8px;
      margin: 0;
    }
    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    .table td {
      padding: 6px 0;
    }
    .table td:last-child {
      text-align: right;
    }
    .total {
      font-weight: bold;
    }
    .net-income {
      background-color: #e6f4f1;
      font-size: 18px;
      font-weight: bold;
      padding: 10px;
      text-align: right;
    }
  </style>
</head>
<body>

<div class="statement">
  <div class="header">
    <h2>{{$user}}</h2>
    <p>Income & Expense Statement</p>
    <p>{{$start_date}} - {{$end_date}}</p>
  </div>

  <div class="section">
    <h3>INCOME</h3>
    <table class="table">
      @foreach($income as $source => $amount)
      <tr><td>{{ $amount->title }}</td><td>£{{ $amount->totalamount }}</td></tr>
      @endforeach
      <tr class="total"><td>Total Income</td><td>£{{$totalincome}}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>EXPENSES</h3>
    <table class="table">
      @foreach($expense as $source => $amount)
      <tr><td>{{ $amount->title }}</td><td>£{{ $amount->totalamount }}</td></tr>
      @endforeach
      <tr class="total"><td>Total Expenses</td><td>£{{$totalexpenditure}}</td></tr>
    </table>
  </div>

  <div class="net-income">
    NET INCOME: £{{$netincome}}
  </div>
</div>

</body>
</html>