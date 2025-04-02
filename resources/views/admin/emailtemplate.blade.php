@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Email/OTP Templates</h1>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="post" action="{{ route('admin.searchemailtemplate') }}">
    @csrf
                    <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" name="str_search" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2"  value="{{ request()->post('str_search') }}">
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
                        <th>Type</th>
                        <th>Subject</th>
                        <th>body</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                    <th>Type</th>
                        <th>Subject</th>
                        <th>body</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
                <tbody>
                @foreach ($template as $item)
                    <tr>
                        <td>{{ $item->key }} - {{$item->type}}</td>
                        <td>{{ $item->subject }}</td>
                        <td>{{ substr($item->body,0,100) }}</td>
                        <td><div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" style="">
                                            <a class="dropdown-item" href="{{ route('admin.getemailtemplate',['templateId'=>$item->id])}}">Edit</a>
                                        </div>
                                    </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
   
        </div>
    </div>
    
</div>
{{ $template->links() }}

@endsection