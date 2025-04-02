@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Subscriptions @if($user != null) : {{$user->name}} @endif</h1>
<div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                        <button class="btn btn-primary" style="float:right"  onclick="window.location.href='{{ route('admin.customer') }}'">
                                    Back
                                </button>
                        </div>
                    </div>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="get" action="{{ route('admin.transactions') }}">
                    <div class="input-group">
                            <label for="start_date">Subscription From:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="start_date" id="start_date" value="{{ request()->get('start_date') }}">
                            <label for="end_date">Subscription To:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="end_date" id="end_date" value="{{ request()->get('end_date') }}">
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
                        <th>Subscription Id</th>
                        <th>Invoice Id</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transaction On</th>
                        <th>Subs. From</th>
                        <th>Subs. To</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Subscription Id</th>
                        <th>Invoice Id</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transaction On</th>
                        <th>Category</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($subscription as $item)
                    <tr>
                        <td>{{ $item->subscription_id }}</td>
                        <td>{{ $item->invoice_id }}</td>
                        <td>&pound;{{ ($item->amount)/100 }}</td>
                        <td>{{ $item->invoice_status }}</td>
                        <td>{{ date('j-m-Y', $item->invoice_date) }}</td>
                        <td>{{ date('j-m-Y', $item->subscription_from) }}</td>
                        <td>{{ date('j-m-Y', $item->subscription_to) }}</td>
                        <td> <a href="{{ $item->invoice_link }}"><i class="fas fa-download fa-sm text-info"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
   
        </div>
    </div>
    
</div>
{{ $subscription->links() }}

@endsection