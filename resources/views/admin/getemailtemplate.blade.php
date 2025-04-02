@extends('layouts.admin')
@section('content')
<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Email/OTP Template: {{$template->key}}</h1>
<!-- DataTales Example -->
<div class="card shadow mb-4">
    <div class="card-body">
    <div class="row">
        <div class="col-lg-6">
            <form class="user" method="post" action="{{ route('admin.editemailtemplate')}}">
                @csrf
                @if (Session::has('success'))
                <div class="alert alert-success">{{ Session::get('success') }}</div>
                @endif
                @if(Session::has('error'))
                <div class="alert alert-danger">{{ Session::get('error') }}</div>
                @endif
                <div class="form-group">
                @if ($template->type == 'Email')
                <label for="subject">Subject</label>
                    <input type="text" name="subject" value="{{$template->subject}}" class="form-control form-control-user @error('subject') is-invalid @enderror"
                        id="subject" aria-describedby="emailHelp"
                        placeholder="Enter Email Subject...">
                        @error('subject')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                        @endif
                <label for="start_date">Body</label>
                        <textarea class="form-control form-control-user @error('body') is-invalid @enderror" name="body" @if ($template->type == 'Email') id="editor" @endif>{{$template->body}}</textarea>
                        @error('body')
                            <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                </div>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" value="Submit">Save
                    </button>&nbsp;
                    <button class="btn btn-primary" type="button" value="Submit" onclick="window.location.href='{{ route('admin.emailtemplate') }}'">Back
                    </button>
                </div>
                <input type="hidden" name="id" value="{{$template->id}}">
                <input type="hidden" name="type" value="{{$template->type}}">
            </form>
        </div>
        <div class="col-lg-3">
            <label for="">Keywords</label><hr style="margin:0"><ul style="list-style-type: none; padding-left: 0;">
            <li></li>
            @foreach($templatevariables as $template)
            <li>{{ $template }}</li>
        @endforeach
        </ul></div>
    </div>
</div>
    
</div>

@endsection