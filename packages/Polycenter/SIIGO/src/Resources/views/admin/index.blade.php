@extends('admin::layouts.content')

@section('page_title')
    {{ __('siigo::app.admin.title') }}
@stop

@section('content')
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ __('siigo::app.admin.title') }}</h1>
            </div>
        </div>

        <div class="page-content">
            <div class="form-container">
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">{{ __('siigo::app.admin.connection-status') }}</div>
                    </div>

                    <div class="panel-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ __('siigo::app.admin.api-url') }}</label>
                                    <p class="form-control-static">{{ config('siigo.base_url') }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ __('siigo::app.admin.environment') }}</label>
                                    <p class="form-control-static">
                                        @if(config('siigo.sandbox'))
                                            <span class="badge badge-warning">Sandbox</span>
                                        @else
                                            <span class="badge badge-success">Production</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <a href="{{ route('admin.siigo.test-connection') }}" class="btn btn-primary">
                                {{ __('siigo::app.admin.test-connection') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">{{ __('siigo::app.admin.sync-actions') }}</div>
                    </div>

                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <form action="{{ route('admin.siigo.sync-customers') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-block">
                                            {{ __('siigo::app.admin.sync-customers') }}
                                        </button>
                                    </form>
                                    <small class="form-text text-muted">
                                        {{ __('siigo::app.admin.sync-customers-description') }}
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <form action="{{ route('admin.siigo.sync-products') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-block">
                                            {{ __('siigo::app.admin.sync-products') }}
                                        </button>
                                    </form>
                                    <small class="form-text text-muted">
                                        {{ __('siigo::app.admin.sync-products-description') }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">{{ __('siigo::app.admin.configuration') }}</div>
                    </div>

                    <div class="panel-body">
                        <form action="{{ route('admin.siigo.save-settings') }}" method="POST">
                            @csrf
                            
                            <div class="form-group">
                                <label for="siigo_client_id">{{ __('siigo::app.admin.client-id') }} *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="siigo_client_id" 
                                       name="siigo_client_id" 
                                       value="{{ config('siigo.client_id') }}" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="siigo_client_secret">{{ __('siigo::app.admin.client-secret') }} *</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="siigo_client_secret" 
                                       name="siigo_client_secret" 
                                       value="{{ config('siigo.client_secret') }}" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="siigo_username">{{ __('siigo::app.admin.username') }} *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="siigo_username" 
                                       name="siigo_username" 
                                       value="{{ config('siigo.username') }}" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="siigo_access_key">{{ __('siigo::app.admin.access-key') }} *</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="siigo_access_key" 
                                       name="siigo_access_key" 
                                       value="{{ config('siigo.access_key') }}" 
                                       required>
                            </div>

                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" 
                                           id="siigo_sandbox" 
                                           name="siigo_sandbox" 
                                           value="1" 
                                           {{ config('siigo.sandbox') ? 'checked' : '' }}>
                                    <label for="siigo_sandbox">{{ __('siigo::app.admin.sandbox-mode') }}</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('siigo::app.admin.save-settings') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
