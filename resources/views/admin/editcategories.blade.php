@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Edit Category: {{$category->title}}</h1>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
    <div class="row">
        <div class="col-lg-6">
            <form class="user" method="post" action="{{ route('admin.updatedategories',['catid'=>$category->id])}}">
                @csrf
                @if (Session::has('success'))
                <div class="alert alert-success">{{ Session::get('success') }}</div>
                @endif
                @if(Session::has('error'))
                <div class="alert alert-danger">{{ Session::get('error') }}</div>
                @endif
                <div class="form-group">
                <label for="title">Title</label>
                    <input type="text" name="title" value="{{$category->title}}" class="form-control form-control-user @error('title') is-invalid @enderror"
                        id="title" aria-describedby="emailHelp"
                        placeholder="Enter Category Title...">
                        @error('title')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                <label for="title">Type</label>
                <select name="type" id="type" class="form-control @error('type') is-invalid @enderror">
                    <option value="income" @if($category->type == 'income') selected @endif>Income</option>
                    <option value="dailyexp" @if($category->type == 'dailyexp') selected @endif>Daily Expenses</option>
                    <option value="recurringexp" @if($category->type == 'recurringexp') selected @endif>Recurring Expenses</option>
                    <option value="paymentmethod" @if($category->type == 'paymentmethod') selected @endif>Payment Method</option>
                    <option value="paymentmethodother" @if($category->type == 'paymentmethodother') selected @endif>Payment Method - income</option>
                </select>
                        @error('type')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                </div>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" value="Submit">Save
                    </button>&nbsp;
                    <button class="btn btn-primary" type="button" value="Submit" onclick="window.location.href='{{ route('admin.categories') }}'">Back
                    </button>
                </div>
                <input type="hidden" name="id" value="{{$category->id}}">
            </form>
        </div>
    </div>
</div>
    
</div>

@endsection