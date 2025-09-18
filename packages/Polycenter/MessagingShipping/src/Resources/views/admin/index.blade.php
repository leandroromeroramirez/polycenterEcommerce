@extends('admin::layouts.content')

@section('page_title')
    Messaging Shipping - Dashboard
@stop

@section('content')
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h1>Messaging Shipping Dashboard</h1>
                <p>Gestión de envíos y mensajería</p>
            </div>
            
            <div class="page-action">
                <a href="{{ route('admin.messaging-shipping.settings') }}" class="btn btn-primary">
                    Configuración
                </a>
                
                <form action="{{ route('admin.messaging-shipping.test-connection') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-info">
                        Probar Conexión
                    </button>
                </form>
            </div>
        </div>
        
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Órdenes</h5>
                        <h3 class="text-primary">{{ $stats['total_orders'] }}</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Pendientes</h5>
                        <h3 class="text-warning">{{ $stats['pending_orders'] }}</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">En Tránsito</h5>
                        <h3 class="text-info">{{ $stats['in_transit_orders'] }}</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Entregados</h5>
                        <h3 class="text-success">{{ $stats['completed_orders'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Órdenes de Envío Recientes</h4>
                    </div>
                    <div class="card-body">
                        @if($shippingOrders->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Orden</th>
                                            <th>Tracking</th>
                                            <th>Estado</th>
                                            <th>Servicio</th>
                                            <th>Costo</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($shippingOrders as $order)
                                            <tr>
                                                <td>{{ $order->id }}</td>
                                                <td>
                                                    @if($order->order)
                                                        <a href="{{ route('admin.sales.orders.view', $order->order->id) }}">
                                                            #{{ $order->order->increment_id }}
                                                        </a>
                                                    @else
                                                        #{{ $order->order_id }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($order->tracking_number)
                                                        <code>{{ $order->tracking_number }}</code>
                                                    @else
                                                        <span class="text-muted">Sin asignar</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $order->status_color }}">
                                                        {{ $order->status_label }}
                                                    </span>
                                                </td>
                                                <td>{{ $order->service_type }}</td>
                                                <td>${{ number_format($order->shipping_cost, 2) }}</td>
                                                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <a href="{{ route('admin.messaging-shipping.show', $order) }}" class="btn btn-sm btn-primary">
                                                        Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            {{ $shippingOrders->links() }}
                        @else
                            <div class="text-center py-4">
                                <h5>No hay órdenes de envío</h5>
                                <p class="text-muted">Las órdenes aparecerán aquí cuando se procesen envíos.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
