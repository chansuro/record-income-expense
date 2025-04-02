@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Millages @if($user != null) : {{$user->name}} @endif</h1>
<div class="row">

                        <!-- Earnings (Monthly) Card Example -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Personal Millage for the period</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{$totalpersonalmillage}}</div>
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
                                                Business Millage for the period</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{$totalbusinessmillage}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-6 mb-4">
                        <button class="btn btn-primary" style="float:right"  onclick="window.location.href='{{ route('admin.customer') }}'">
                                    Back
                                </button>
                        </div>
</div>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="get" action="{{ route('admin.millage') }}">
                    <div class="input-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" class="form-control bg-light border-0 small" name="start_date" id="start_date" value="{{ request()->get('start_date') }}">
                            <label for="end_date">End Date:</label>
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
                        <th>Business Millage</th>
                        <th>Personal Millage</th>
                        <th>Millage On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>User</th>
                        <th>Business Millage</th>
                        <th>Personal Millage</th>
                        <th>Millage On</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($millage as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->business_millage }}</td>
                        <td>{{ $item->personal_millage }}</td>
                        <td>{{ date('j-m-Y', strtotime($item->millage_date)) }}</td>
                        <td> <a href="{{ route('admin.millageidwise',['millageId'=>$item->id]) }}"><i class="fas fa-eye fa-sm text-info"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
   
        </div>
    </div>
    
</div>
{{ $millage->links() }}

@endsection