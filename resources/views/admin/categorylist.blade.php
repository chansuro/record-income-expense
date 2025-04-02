@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Categories</h1>
<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="get" action="{{ route('admin.categories') }}">
                    <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" name="str_search" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2"  value="{{ request()->get('str_search') }}">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                    </div>
                </form>
                <a href="{{ route('admin.addcategory') }}" class="btn btn-primary" type="button" style="float:right">Add Category</a>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                    <th>Title</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($categorylist as $item)
                    <tr>
                        
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->type }}</td>
                        <td>{{ $item->name }}</td>
                        <td> @if($item->name == null)<a href="{{ route('admin.editcategories',['catid'=>$item->id]) }}"><i class="fas fa-pen fa-sm text-info"></i></a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                    <th>Title</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </tfoot>
            </table>
   
        </div>
    </div>
    
</div>
{{ $categorylist->links() }}

@endsection