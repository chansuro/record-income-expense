@extends('layouts.admin')
@section('content')
@section('title', 'HMRC Integration')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">HMRC Integration</h4>
            <p class="text-muted mb-0">
                Manage the connection between AppTax and HMRC.
            </p>
        </div>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>
        </div>
    @endif

    {{-- Error Message --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>
        </div>
    @endif


    <div class="row">

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">

                        <h5 class="mb-0">
                            HMRC Agent Connection
                        </h5>

                        @if($connection && $connection->is_active)
                            <span class="badge bg-success">
                                Connected
                            </span>
                        @else
                            <span class="badge bg-danger">
                                Not Connected
                            </span>
                        @endif

                    </div>
                </div>


                <div class="card-body">

                    @if($connection && $connection->is_active)

                        <div class="alert alert-success">
                            <strong>Connected to HMRC</strong>

                            <div class="mt-1">
                                AppTax is currently connected to the HMRC Agent Services Account.
                            </div>
                        </div>


                        <div class="table-responsive">

                            <table class="table table-borderless">

                                <tbody>

                                    <tr>
                                        <th width="35%">
                                            Status
                                        </th>

                                        <td>
                                            <span class="badge bg-success">
                                                Connected
                                            </span>
                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Environment
                                        </th>

                                        <td>
                                            @if($connection->environment === 'production')

                                                <span class="badge bg-success">
                                                    Production
                                                </span>

                                            @else

                                                <span class="badge bg-warning text-dark">
                                                    Sandbox
                                                </span>

                                            @endif
                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Agent Reference Number
                                        </th>

                                        <td>
                                            @if($connection->arn)

                                                {{ substr($connection->arn, 0, 2) }}
                                                ******
                                                {{ substr($connection->arn, -3) }}

                                            @else

                                                <span class="text-muted">
                                                    Not available
                                                </span>

                                            @endif
                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Connected On
                                        </th>

                                        <td>
                                            @if($connection->connected_at)

                                                {{ $connection->connected_at->format('d M Y, h:i A') }}

                                            @else

                                                -
                                            @endif
                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Access Token
                                        </th>

                                        <td>

                                            @if(
                                                $connection->expires_at &&
                                                $connection->expires_at->isFuture()
                                            )

                                                <span class="badge bg-success">
                                                    Active
                                                </span>

                                            @else

                                                <span class="badge bg-warning text-dark">
                                                    Expired
                                                </span>

                                            @endif

                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Token Expires At
                                        </th>

                                        <td>
                                            @if($connection->expires_at)

                                                {{ $connection->expires_at->format('d M Y, h:i A') }}

                                            @else

                                                -
                                            @endif
                                        </td>
                                    </tr>


                                    <tr>
                                        <th>
                                            Authorised Scopes
                                        </th>

                                        <td>

                                            @if($connection->scope)

                                                @foreach(explode(' ', $connection->scope) as $scope)

                                                    <span class="badge bg-secondary me-1 mb-1">
                                                        {{ $scope }}
                                                    </span>

                                                @endforeach

                                            @else

                                                <span class="text-muted">
                                                    No scope information available.
                                                </span>

                                            @endif

                                        </td>
                                    </tr>

                                </tbody>

                            </table>

                        </div>


                        <hr>


                        <div class="d-flex gap-2">

                            <a
                                href="{{ route('hmrc.authorize') }}"
                                class="btn btn-primary">

                                Reconnect HMRC

                            </a>

                        </div>

                    @else

                        <div class="text-center py-5">

                            <div class="mb-3">
                                <i class="fas fa-link fa-3x text-muted"></i>
                            </div>

                            <h5>
                                AppTax is not connected to HMRC
                            </h5>

                            <p class="text-muted mb-4">
                                Connect the AppTax HMRC Agent Services Account
                                before accessing or submitting client tax information.
                            </p>

                            <a
                                href="{{ route('hmrc.authorize') }}"
                                class="btn btn-primary">

                                Connect HMRC

                            </a>

                        </div>

                    @endif

                </div>

            </div>

        </div>


        <div class="col-lg-4">

            <div class="card shadow-sm">

                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        HMRC Configuration
                    </h5>
                </div>

                <div class="card-body">

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Environment
                        </small>

                        @if(config('services.hmrc.environment') === 'production')

                            <span class="badge bg-success">
                                Production
                            </span>

                        @else

                            <span class="badge bg-warning text-dark">
                                Sandbox
                            </span>

                        @endif

                    </div>


                    <hr>


                    <div class="mb-3">

                        <small class="text-muted d-block mb-1">
                            Agent Reference Number
                        </small>

                        @php
                            $arn = config('services.hmrc.arn');
                        @endphp

                        @if($arn)

                            <strong>
                                {{ substr($arn, 0, 2) }}
                                ******
                                {{ substr($arn, -3) }}
                            </strong>

                        @else

                            <span class="text-danger">
                                ARN not configured
                            </span>

                        @endif

                    </div>


                    <hr>


                    <div>
                        <small class="text-muted">
                            HMRC access and refresh tokens are stored securely
                            by AppTax and are never displayed in the administrator portal.
                        </small>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
@endsection

