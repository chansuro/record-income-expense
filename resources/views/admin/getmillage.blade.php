@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
 <a type="button" class="btn btn-primary" style="float:right" href="{{ url()->previous() }}">Back</a>
<h1 class="h3 mb-4 text-gray-800">Millage details</h1>

<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
    <div class="row">
        <div class="col-lg-12">
                <div class="form-group">
                <div class="col-xl-12 col-md-12 mb-12">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-top p-2">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Business Millage</div>
                                            <div class="mb-0 text-gray-800">{{$millages->business_millage}}</div>
                                        </div>
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Personal Millage</div>
                                            <div class="mb-0 text-gray-800">{{$millages->personal_millage}}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row no-gutters align-items-top p-2">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Millage Date</div>
                                            <div class="mb-0 text-gray-800">{{ date('j-m-Y', strtotime($millages->millage_date)) }}</div>
                                        </div>
                                        
                                    </div>
                                    <div class="row no-gutters align-items-top p-2">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            User</div>
                                            <div class="mb-0 text-gray-800">{{$millages->name}}</div>
                                        </div>
                                        @if($millages->document != null)
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Image</div>
                                            <div class="mb-0  text-gray-800"><img src="{{$millages->document}}" alt="" style="width:100px; cursor:pointer"  data-bs-toggle="modal" data-bs-target="#exampleModal"></div>
                                        </div>
                                        @endif
                                        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                    <img src="{{$millages->document}}" alt="" style="width:100%;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                </div>
        </div>
    </div>
</div>
    
</div>

@endsection