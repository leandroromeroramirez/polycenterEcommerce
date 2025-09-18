# 📦 Guía Completa: MessagingShipping Package en Bagisto

## 🎯 ¿Qué es MessagingShipping?

MessagingShipping es un package personalizado que integra servicios de mensajería y envíos (como Envia.com) directamente en el proceso de ventas de Bagisto. Permite calcular costos de envío en tiempo real, crear guías de envío automáticamente y hacer seguimiento de paquetes.

## 🔄 Flujo Completo del Proceso de Ventas

### 1. **CLIENTE EN EL CARRITO DE COMPRAS**
```
Cliente agrega productos → Va al checkout → Introduce dirección de envío
                                                        ↓
                                    Package MessagingShipping calcula costos automáticamente
```

### 2. **CÁLCULO DE ENVÍO EN TIEMPO REAL**
Cuando el cliente introduce su dirección:

```php
// Automáticamente se llama a:
POST /api/v1/messaging-shipping/shipping-rates

// Con datos como:
{
  "origin": {
    "city_code": "11001",      // Bogotá (tu tienda)
    "postal_code": "110111"
  },
  "destination": {
    "city_code": "05001",      // Medellín (cliente)
    "postal_code": "050001"
  },
  "packages": [{
    "weight": 1.5,
    "height": 10,
    "width": 15,
    "length": 20
  }],
  "declared_value": 100000
}
```

### 3. **OPCIONES DE ENVÍO DISPONIBLES**
El sistema muestra al cliente:
- ✅ **Envío Estándar**: $15,000 - Entrega en 3-5 días
- ✅ **Envío Express**: $25,000 - Entrega en 1-2 días
- ⚡ **Envío Nocturno**: $35,000 - Entrega al día siguiente
- 🚚 **Mismo Día**: $50,000 - Solo ciudades principales

### 4. **CLIENTE COMPLETA LA COMPRA**
```
Cliente selecciona método de envío → Paga → Orden se crea en Bagisto
                                                        ↓
                                    Se dispara evento OrderPlaced
                                                        ↓
                                MessagingShipping crea la guía automáticamente
```

### 5. **CREACIÓN AUTOMÁTICA DE GUÍA DE ENVÍO**
```php
// Automáticamente se ejecuta:
POST /api/v1/messaging-shipping/shipping-orders

// Se crea registro en base de datos:
messaging_shipping_orders table:
- order_id: 12345
- tracking_number: "ENV123456789"
- status: "created"
- shipping_cost: 15000
- service_type: "standard"
```

## 🗄️ Estructura de Base de Datos

### Tabla: `messaging_shipping_orders`
```sql
- id (autoincrement)
- order_id (relación con orders de Bagisto)
- tracking_number (número de seguimiento)
- service_type (standard, express, overnight, same_day)
- status (created, confirmed, picked_up, in_transit, delivered, etc.)
- shipping_cost (costo del envío)
- origin_data (JSON con datos de origen)
- destination_data (JSON con datos de destino)
- package_data (JSON con información del paquete)
- external_id (ID en el sistema de Envia.com)
- created_at / updated_at
```

### Tabla: `messaging_shipping_rates`
```sql
- id
- origin_city_code
- destination_city_code
- service_type
- base_cost
- weight_factor
- distance_factor
- is_active
- created_at / updated_at
```

### Tabla: `messaging_shipping_tracking`
```sql
- id
- messaging_shipping_order_id
- status
- status_description
- location
- timestamp
- notes
- created_at / updated_at
```

## 🎮 Panel de Administración

### Acceso: `http://localhost/admin/messaging-shipping`

#### **Dashboard Principal**
- 📊 **Estadísticas en tiempo real**:
  - Total de envíos del mes
  - Envíos pendientes
  - Envíos en tránsito
  - Envíos entregados

- 📋 **Lista de órdenes**:
  - Todas las órdenes con envío
  - Filtros por estado, fecha, tipo de servicio
  - Acciones rápidas (cancelar, actualizar estado)

#### **Configuración** (`/admin/messaging-shipping/settings`)
```php
// Configuraciones que puedes ajustar:
- API Key de Envia.com
- API Secret
- URL de la API (sandbox/producción)
- Configuración de origen (tu dirección de tienda)
- Activar/desactivar tipos de servicio
- Configurar precios base
- Configurar webhooks
```

#### **Prueba de Conexión**
```bash
# Botón "Test Connection" ejecuta:
POST /admin/messaging-shipping/test-connection

# Verifica:
✅ Conectividad con API de Envia.com
✅ Autenticación correcta
✅ Endpoints disponibles
```

## 🔌 APIs Disponibles

### **Para el Frontend/Checkout**
```http
POST /api/v1/messaging-shipping/shipping-rates
# Calcula costos de envío en tiempo real

GET /api/v1/messaging-shipping/tracking/{numero}
# Permite al cliente rastrear su paquete
```

### **Para Administración**
```http
POST /api/v1/messaging-shipping/shipping-orders
# Crea guía de envío (automático tras compra)

GET /api/v1/messaging-shipping/shipping-orders/{id}
# Obtiene estado de una orden específica

POST /api/v1/messaging-shipping/shipping-orders/{id}/cancel
# Cancela una guía de envío
```

### **Webhook de Envia.com**
```http
POST /api/v1/messaging-shipping/webhook
# Recibe actualizaciones automáticas de estado desde Envia.com
```

## 🔄 Eventos y Automatización

### **Eventos de Bagisto que Escucha**
```php
// En MessagingShippingServiceProvider.php
Event::listen('sales.order.save.after', function ($order) {
    // Cuando se crea una orden, automáticamente:
    // 1. Crea la guía en Envia.com
    // 2. Guarda tracking number
    // 3. Actualiza estado de la orden
});

Event::listen('sales.shipment.save.after', function ($shipment) {
    // Cuando se marca como enviado:
    // 1. Notifica a Envia.com
    // 2. Activa seguimiento
    // 3. Envía email al cliente
});
```

### **Eventos que Dispara el Package**
```php
// Cuando cambia estado de envío:
Event::dispatch('messaging-shipping.status.updated', [
    'order_id' => $orderId,
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'tracking_number' => $trackingNumber
]);

// Cuando se entrega el paquete:
Event::dispatch('messaging-shipping.delivered', [
    'order_id' => $orderId,
    'delivery_date' => now(),
    'recipient_name' => $recipientName
]);
```

## 🎯 Integración con Bagisto Core

### **Como Método de Envío**
```php
// El package se registra automáticamente como carrier
'carriers' => [
    'messaging-shipping' => [
        'title' => 'Messaging Shipping',
        'class' => 'Polycenter\MessagingShipping\Carriers\MessagingShipping',
        'active' => true,
    ]
]
```

### **En el Checkout**
1. Cliente ve opciones de envío calculadas en tiempo real
2. Selecciona el método preferido
3. Se incluye en el total de la orden
4. Al completar compra, se crea guía automáticamente

### **En las Órdenes**
- Campo adicional: "Número de Seguimiento"
- Estado de envío sincronizado
- Botones de acción (cancelar envío, actualizar estado)
- Link directo para rastreo

## 📱 Experiencia del Cliente

### **Durante la Compra**
```
1. Agrega productos al carrito
2. Va al checkout
3. Introduce dirección → Sistema calcula envíos automáticamente
4. Ve opciones: "Estándar $15k", "Express $25k"
5. Selecciona y paga
6. Recibe email con número de seguimiento
```

### **Después de la Compra**
```
1. Recibe SMS/Email: "Tu pedido #12345 ha sido enviado"
2. Puede rastrear en: yourstore.com/tracking/ENV123456789
3. Recibe notificaciones de estado: "En tránsito", "En reparto", "Entregado"
```

## 🔧 Configuración Recomendada

### **Variables de Entorno** (ya las tienes configuradas)
```env
MESSAGING_SHIPPING_API_KEY=23b4426ceee4344e5ef6cb0d015f33864a1e2c66e50e97a58eb00f5501080142
MESSAGING_SHIPPING_API_SECRET=23b4426ceee4344e5ef6cb0d015f33864a1e2c66e50e97a58eb00f5501080142
MESSAGING_SHIPPING_API_URL=https://api-test.envia.com/
MESSAGING_SHIPPING_SANDBOX=true
```

### **Configuración en Admin Panel**
1. Ve a: `http://localhost/admin/messaging-shipping/settings`
2. Verifica que las credenciales están correctas
3. Configura tu dirección de origen (dirección de tu tienda)
4. Activa los tipos de servicio que quieres ofrecer
5. Ajusta precios base si es necesario
6. Prueba la conexión

## 🚀 Para Activar en Producción

### **Paso 1: Cambiar a API de Producción**
```env
MESSAGING_SHIPPING_SANDBOX=false
MESSAGING_SHIPPING_API_URL=https://api.envia.com/
```

### **Paso 2: Activar en Bagisto**
```
Admin → Configuración → Métodos de Envío → Messaging Shipping → Activar
```

### **Paso 3: Configurar Webhook**
```
En tu panel de Envia.com:
URL: https://tu-tienda.com/api/v1/messaging-shipping/webhook
Secret: tu_webhook_secret
```

## 🎉 ¡Listo para Funcionar!

Con esta configuración, cada vez que un cliente compre en tu tienda:
1. ✅ Se calculará el envío automáticamente
2. ✅ Se creará la guía en Envia.com
3. ✅ El cliente recibirá su número de seguimiento
4. ✅ Podrá rastrear su paquete en tiempo real
5. ✅ Recibirá notificaciones de cambios de estado

¿Te gustaría que hagamos una prueba completa del flujo o que explique alguna parte específica en más detalle?
