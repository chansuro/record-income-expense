@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Transactions @if($user != null) : {{$user->name}} @endif</h1>
<div class="row">

                        <!-- Earnings (Monthly) Card Example -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Income for the period</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">&pound;{{$totalIncome}}</div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Earnings (Annual) Card Example -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Expenses for the period</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">&pound;{{$totalExpenses}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Requests Card Example -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Profit for the period</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">&pound;{{$totalProfit}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                        <button class="btn btn-primary" style="float:right"  onclick="window.location.href='{{ route('admin.customer') }}'">
                                    Back
                                </button>
                        </div>
                    </div>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="get" action="{{ route('admin.transactions') }}">
                    <div class="input-group">
                            <label for="start_date">Transaction From:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="start_date" id="start_date" value="{{ request()->get('start_date') }}">
                            <label for="end_date">Transaction To:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="end_date" id="end_date" value="{{ request()->get('end_date') }}">
                            <input type="text" class="form-control bg-light border-0 small" name="str_search" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2"  value="{{ request()->get('str_search') }}">
                            <input type="hidden" name="u" value="{{ request()->get('u') }}">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                    </div>
                </form>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Transaction On</th>
                        <th>Category</th>
                        <th>Recurring</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>User</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Transaction On</th>
                        <th>Category</th>
                        <th>Recurring</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($transaction as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->title }}</td>
                        <td>&pound;{{ $item->amount }}</td>
                        <td>{{ $item->type }}</td>
                        <td>{{ date('j-m-Y', strtotime($item->transaction_date)) }}</td>
                        <td>{{ $item->catecory_name }}</td>
                        <td>@if ($item->is_recurring == 'Y')
                            {{$item->recurring_period}}
                        @else
                            No
                        @endif</td>
                        <td>{{ $item->paymentmethod }}</td>
                        <td> <a href="{{ route('admin.transactionsidwise',['transactionid'=>$item->id]) }}"><i class="fas fa-eye fa-sm text-info"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
   
        </div>
    </div>
    
</div>
{{ $transaction->links() }}

@endsection