@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Users</h1>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="post" action="{{ route('admin.searchcustomer') }}">
    @csrf
                    <div class="input-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="start_date" id="start_date" value="{{ request()->post('start_date') }}">
                            <label for="end_date">End Date:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="end_date" id="end_date" value="{{ request()->post('end_date') }}">
                            <input type="text" class="form-control bg-light border-0 small" name="str_search" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2"  value="{{ request()->post('str_search') }}">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                    </div>
                </form>
@if (Session::has('success'))
<div class="alert alert-success">{{ Session::get('success') }}</div>
@endif
@error('user_id')
<div class="alert alert-danger">Please select the User to suspend.</div>
@enderror
@error('suspendreason')
<div class="alert alert-danger">Please provide suspension reasons.</div>
@enderror
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($user as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->email }}</td>
                        <td>{{ $item->phone }}</td>
                        <td>{{ date('j-m-Y', strtotime($item->created_at)) }}</td>
                        <td>
                        @if ($item->status == 1)
                            Active
                        @else
                            Inactive
                        @endif</td>
                        <div class="modal fade" id="suspandModal{{ $item->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Ready to suspend {{ $item->name }}?</h5>
                                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>
                                    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="post" action="{{ route('admin.suspendcustomer')}}">
                                    @csrf  
                                        <div class="modal-body"><label for="suspendreason">Please specify suspend reason</label>
                                            <textarea name="suspendreason" id="suspendreason" class="form-control form-control-user "></textarea>
                                            <input type="hidden" name="user_id" value="{{ $item->id }}">
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                                            <button class="btn btn-danger" type="submit">Suspend</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <td><div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" style="">
                                            <a class="dropdown-item" href="{{ route('admin.editcustomer',['userid'=>$item->id]) }}">Edit</a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#suspandModal{{ $item->id }}">Suspend</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="{{ route('admin.transactions')}}?u={{$item->id}}">Transactions</a>
                                            <a class="dropdown-item" href="{{ route('admin.millage')}}?u={{$item->id}}">Millage</a>
                                            <a class="dropdown-item" href="{{ route('admin.subscriptions')}}?u={{$item->id}}">Subscriptions</a>
                                        </div>
                                    </div> <!--<a class="btn btn-success btn-circle btn-sm"><i class="fa fa-pen"></i></a>&nbsp;<a class="btn btn-info btn-circle btn-sm"><i class="fa fa-eye"></i></a>&nbsp;<a class="btn btn-danger btn-circle btn-sm"><i class="fas fa-trash"></i></a> --></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
   
        </div>
    </div>
    
</div>

{{ $user->links() }}

@endsection