# 🚀 Guía de Uso - API MessagingShipping

## 📌 **URL Base de la API**
```
http://localhost/api/v1/messaging-shipping
```

## 🔑 **Endpoints Disponibles**

### 1. **Test de Conexión** 
**Endpoint:** `GET /test-connection`  
**Descripción:** Verifica que la API esté funcionando correctamente

```bash
curl -X GET "http://localhost/api/v1/messaging-shipping/test-connection" \
-H "Accept: application/json"
```

**Respuesta:**
```json
{
  "status": "success",
  "data": {
    "success": true,
    "message": "Sandbox mode: Connection test simulated successfully",
    "environment": "test",
    "api_url": "https://api-test.envia.com/",
    "auth_method": "mock"
  }
}
```

---

### 2. **Calcular Tarifas de Envío** ⭐
**Endpoint:** `POST /shipping-rates`  
**Descripción:** Calcula las tarifas de envío entre dos ubicaciones

```bash
curl -X POST "http://localhost/api/v1/messaging-shipping/shipping-rates" \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-d '{
  "origin": {
    "city_code": "11001",
    "postal_code": "110111",
    "city": "Bogotá",
    "state": "Bogotá D.C.",
    "country": "CO"
  },
  "destination": {
    "city_code": "050001",
    "postal_code": "050001", 
    "city": "Medellín",
    "state": "Antioquia",
    "country": "CO"
  },
  "packages": [
    {
      "weight": 2.5,
      "length": 30,
      "width": 20,
      "height": 15,
      "declared_value": 150000
    }
  ]
}'
```

**Parámetros requeridos:**
- `origin.city_code` (string): Código de ciudad origen
- `origin.postal_code` (string): Código postal origen
- `destination.city_code` (string): Código de ciudad destino
- `destination.postal_code` (string): Código postal destino
- `packages` (array): Array de paquetes
- `packages[].weight` (numeric): Peso en kg (mín: 0.1)
- `packages[].length` (numeric): Largo en cm (mín: 1)
- `packages[].width` (numeric): Ancho en cm (mín: 1)
- `packages[].height` (numeric): Alto en cm (mín: 1)
- `packages[].declared_value` (numeric): Valor declarado en COP

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "rates": [
      {
        "service": "Envío Estándar",
        "service_code": "standard",
        "price": 15000,
        "currency": "COP",
        "delivery_time": "3-5 días hábiles",
        "estimated_delivery": "2025-09-22",
        "carrier": "Envia.com"
      },
      {
        "service": "Envío Express",
        "service_code": "express",
        "price": 25000,
        "currency": "COP", 
        "delivery_time": "1-2 días hábiles",
        "estimated_delivery": "2025-09-20",
        "carrier": "Envia.com"
      }
    ],
    "sandbox": true
  }
}
```

---

### 3. **Seguimiento de Envíos**
**Endpoint:** `GET /tracking/{trackingNumber}`  
**Descripción:** Obtiene el estado y historial de un envío

```bash
curl -X GET "http://localhost/api/v1/messaging-shipping/tracking/ENV123456789" \
-H "Accept: application/json"
```

**Respuesta (exitosa):**
```json
{
  "success": true,
  "data": {
    "tracking_number": "ENV123456789",
    "status": "in_transit",
    "carrier": "Envia.com",
    "events": [
      {
        "date": "2025-09-17",
        "status": "picked_up",
        "description": "Paquete recolectado",
        "location": "Bogotá"
      },
      {
        "date": "2025-09-18",
        "status": "in_transit",
        "description": "En tránsito",
        "location": "Centro de distribución"
      }
    ]
  }
}
```

---

### 4. **Crear Orden de Envío** 🔒
**Endpoint:** `POST /shipping-orders`  
**Autenticación:** Requiere token Sanctum  
**Descripción:** Crea una nueva orden de envío

```bash
curl -X POST "http://localhost/api/v1/messaging-shipping/shipping-orders" \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-H "Authorization: Bearer TU_TOKEN_AQUI" \
-d '{
  "order_id": 123,
  "service_type": "standard",
  "recipient": {
    "name": "Juan Pérez",
    "phone": "3001234567",
    "email": "juan@example.com",
    "address": "Carrera 45 #67-89",
    "city": "Medellín",
    "state": "Antioquia",
    "postal_code": "050001"
  },
  "packages": [
    {
      "weight": 1.5,
      "length": 20,
      "width": 15,
      "height": 10,
      "declared_value": 100000
    }
  ]
}'
```

---

### 5. **Webhook para Actualizaciones**
**Endpoint:** `POST /webhook`  
**Descripción:** Recibe notificaciones automáticas de cambios de estado

```json
{
  "tracking_number": "ENV123456789",
  "status": "delivered",
  "timestamp": "2025-09-20T14:30:00Z",
  "location": "Medellín, Antioquia",
  "signature": "hash_verification"
}
```

---

## 🛠️ **Ejemplos de Uso Prácticos**

### **Ejemplo 1: Cotizar envío para e-commerce**
```javascript
// JavaScript/Node.js
const calculateShipping = async (origin, destination, packages) => {
  const response = await fetch('http://localhost/api/v1/messaging-shipping/shipping-rates', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      origin,
      destination, 
      packages
    })
  });
  
  const data = await response.json();
  return data.data.rates;
};

// Uso
const rates = await calculateShipping(
  { city_code: "11001", postal_code: "110111" },
  { city_code: "050001", postal_code: "050001" },
  [{ weight: 1.5, length: 20, width: 15, height: 10, declared_value: 100000 }]
);
```

### **Ejemplo 2: Integración con PHP/Laravel**
```php
// PHP
use Illuminate\Support\Facades\Http;

class ShippingService 
{
    public function getShippingRates($origin, $destination, $packages)
    {
        $response = Http::post('http://localhost/api/v1/messaging-shipping/shipping-rates', [
            'origin' => $origin,
            'destination' => $destination,
            'packages' => $packages
        ]);
        
        return $response->json()['data']['rates'];
    }
}
```

### **Ejemplo 3: Seguimiento con Python**
```python
# Python
import requests

def track_shipment(tracking_number):
    url = f"http://localhost/api/v1/messaging-shipping/tracking/{tracking_number}"
    response = requests.get(url, headers={'Accept': 'application/json'})
    
    if response.status_code == 200:
        return response.json()['data']
    else:
        return None
```

---

## 🔍 **Códigos de Respuesta**

| Código | Descripción |
|---------|-------------|
| `200` | Solicitud exitosa |
| `422` | Error de validación (datos incorrectos) |
| `500` | Error interno del servidor |
| `404` | Endpoint no encontrado |

---

## 🚨 **Manejo de Errores**

### **Error de Validación (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "packages.0.weight": ["El campo weight es obligatorio."],
    "origin.city_code": ["El campo city_code es obligatorio."]
  }
}
```

### **Error del Servidor (500):**
```json
{
  "success": false,
  "message": "Failed to calculate shipping rates",
  "error": "Connection timeout"
}
```

---

## 🌐 **Códigos de Ciudades Principales (Colombia)**

| Ciudad | Código |
|--------|--------|
| Bogotá | 11001 |
| Medellín | 050001 |
| Cali | 76001 |
| Barranquilla | 080001 |
| Cartagena | 130001 |
| Bucaramanga | 680001 |
| Pereira | 660001 |
| Santa Marta | 470001 |

---

## 📞 **Soporte y Testing**

### **Comando de Testing:**
```bash
php artisan messaging-shipping:test-envia
```

### **Dashboard Administrativo:**
```
http://localhost/admin/messaging-shipping
```

### **Logs:**
```bash
tail -f storage/logs/laravel.log | grep MessagingShipping
```

---

## 🔧 **Configuración Avanzada**

Para cambiar a modo producción con credenciales reales:

1. **Actualizar .env:**
```env
MESSAGING_SHIPPING_SANDBOX=false
MESSAGING_SHIPPING_API_KEY=tu_api_key_real
MESSAGING_SHIPPING_API_SECRET=tu_api_secret_real
```

2. **Limpiar cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

3. **Probar conexión:**
```bash
php artisan messaging-shipping:test-envia
```

---

**¡La API está lista para usar! 🚀**
