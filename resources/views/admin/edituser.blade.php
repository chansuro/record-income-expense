@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Edit User: {{$user->name}}</h1>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
    <div class="row">
        <div class="col-lg-6">
            <form class="user" method="post" action="">
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
            </form>
        </div>
    </div>
</div>
    
</div>

@endsection