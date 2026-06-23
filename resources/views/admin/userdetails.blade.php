@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">{{$user->name}}</h1>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
    <div class="row">
        @if($user->avatar)
        <div class="col-lg-6">
                <p><img src="/avatar_images/{{$user->avatar}}" width="100" alt=""> </p>
        </div><div class="col-lg-6"></div>  @endif
        <div class="col-lg-3">
            <label for="name">Name</label>
            <p>{{$user->name}}</p>
        </div>
        <div class="col-lg-3">
            <label for="email">Email</label>
            <p>{{$user->email}}</p>
        </div>
        <div class="col-lg-3">
            <label for="phone">Phone</label>
            <p>{{$user->phone}}</p>
        </div>
        <div class="col-lg-3">
            <label for="phone">Status</label>
            <p>@if ($user->status == 1)
                            Active
                        @elseif($user->status == 3)
                            Suspended
                        @elseif($user->status == 4)
                            Subscription Expired
                        @elseif($user->status == 5)
                            Subscription Cancelled
                        @endif</p>
        </div>
        <div class="col-lg-3">
            <label for="created_at">Registered On</label>
            <p>{{ date('j-m-Y', strtotime($user->created_at)) }}</p>
        </div>
        <div class="col-lg-3">
            <label for="created_at">Platform</label>
            <p>{{ $user->platform }}</p>
        </div>
        <div class="col-lg-3">
            <label for="my_ref_code">Ref Code</label>
            <p>{{$user->my_ref_code ?? 'N/A'}}</p>
        </div>

        <div class="col-lg-3">
           
            <!-- <label for="avatar">Avatar</label>
             @if($user->avatar)
            <p><img src="/avatar_images/{{$user->avatar}}" width="100" alt=""> </p>@endif -->
            
        </div>
        @if($user->status == 3 || $user->status == 4 || $user->status == 5)
           <div class="col-lg-6">
            <label for="suspend_reason">Suspension Reason</label>
            <p>{{$user->suspend_reason ?? 'N/A'}}</p>
        </div> 
        @endif
        <h1 class="h3 mb-4 text-gray-800">Billing Details</h1>
        <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Form</th>
                        <th>To</th>
                        <th>Invoice Date</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Form</th>
                        <th>To</th>

                        <th>Invoice Date</th>
                        <th>Invoice</th>
                    </tr>
                </tfoot>
                <tbody>
                    </tbody>
                    @foreach ($billing as $item)
                    
                    <tr>
                        <td>{{ $item['subscription_id'] }}</td>
                        <td>{{ $item['amount'] }}</td>
                        <td>{{ $item['invoice_status'] }}</td>
                        <td>{{ date('j-m-Y', $item['subscription_from']) }}</td>
                        <td>{{ date('j-m-Y', $item['subscription_to']) }}</td>
                        <td>{{ date('j-m-Y', strtotime($item['created_at'])) }}</td>
                        <td><a href="{{ $item['invoice_link'] }}" target="_blank">View Invoice</a></td>
                        
                    </tr>
                    @endforeach
            </table>

            <!-- <form class="user" method="post" action="">
                @csrf
                @if (Session::has('success'))
                <div class="alert alert-success">{{ Session::get('success') }}</div>
                @endif
                @if(Session::has('error'))
                <div class="alert alert-danger">{{ Session::get('error') }}</div>
                @endif
                <div class="form-group">
                <label for="name">Name</label>
                    <input type="text" name="name" value="{{$user->name}}" class="form-control form-control-user @error('name') is-invalid @enderror"
                        id="name" aria-describedby="emailHelp"
                        placeholder="Enter User Name...">
                        @error('name')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                <label for="email">Email</label>
                    <input type="text" name="email" value="{{$user->email}}" class="form-control form-control-user @error('email') is-invalid @enderror"
                        id="email" aria-describedby="emailHelp"
                        placeholder="Enter User Email...">
                        @error('email')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                <label for="phone">Phone</label>
                    <input type="text" name="phone" value="{{$user->phone}}" class="form-control form-control-user @error('phone') is-invalid @enderror"
                        id="phone" aria-describedby="emailHelp"
                        placeholder="Enter User Phone...">
                        @error('phone')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                        @if($user->avatar)
                <label for="avatar">Avatar</label>
                <div><img src="/avatar_images/{{$user->avatar}}" width="100" alt=""> </div>
                        
                    @endif
                </div>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" value="Submit">Save
                    </button>&nbsp;
                    <button class="btn btn-primary" type="button" value="Submit" onclick="window.location.href='{{ route('admin.customer') }}'">Back
                    </button>
                </div>
                <input type="hidden" name="id" value="{{$user->id}}">
            </form> -->
        
    </div>
</div>
    
</div>

@endsection