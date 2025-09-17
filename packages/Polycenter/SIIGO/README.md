# SIIGO Integration Package for Bagisto

Este paquete permite la integración entre Bagisto y SIIGO (software de contabilidad colombiano) para sincronizar clientes, productos y facturas.

## Características

- ✅ Autenticación OAuth con SIIGO API
- ✅ Sincronización de clientes de Bagisto a SIIGO
- ✅ Sincronización de productos de Bagisto a SIIGO
- ✅ Creación de facturas en SIIGO
- ✅ Mapeo de datos entre sistemas
- ✅ Gestión de errores y reintentos
- ✅ Interfaz administrativa para configuración
- ✅ Webhooks para sincronización bidireccional
- ✅ Seguimiento del estado de sincronización

## Instalación

### 1. Instalación del Paquete

El paquete ya está incluido en tu proyecto Bagisto en `packages/Polycenter/SIIGO/`.

### 2. Configuración de Autoloading

Ya agregado en `composer.json`:
```json
"autoload": {
    "psr-4": {
        "Polycenter\\SIIGO\\": "packages/Polycenter/SIIGO/src"
    }
}
```

### 3. Registro del Service Provider

Ya agregado en `bootstrap/providers.php`:
```php
Polycenter\SIIGO\Providers\SIIGOServiceProvider::class,
```

### 4. Configuración del Entorno

Agrega las siguientes variables a tu archivo `.env`:

```env
SIIGO_CLIENT_ID=tu_client_id
SIIGO_CLIENT_SECRET=tu_client_secret
SIIGO_USERNAME=tu_usuario
SIIGO_ACCESS_KEY=tu_access_key
SIIGO_SANDBOX=true
SIIGO_BASE_URL="https://api.siigo.com/v1"
```

### 5. Ejecutar Migraciones

```bash
php artisan migrate
```

Esto creará la tabla `siigo_mappings` para el seguimiento de sincronización.

### 6. Regenerar Autoloader

```bash
composer dump-autoload
```

## Configuración

### Obtener Credenciales de SIIGO

1. Registrate en [SIIGO](https://www.siigo.com)
2. Ve a la sección de API/Integraciones
3. Crea una nueva aplicación
4. Obtén tu `Client ID`, `Client Secret`, `Username` y `Access Key`

### Configuración en Bagisto

1. Ve al panel de administración de Bagisto
2. Navega a **Sistema → Integración SIIGO**
3. Ingresa tus credenciales de SIIGO
4. Prueba la conexión
5. Configura las opciones de sincronización

## Uso

### Sincronización Manual

Desde el panel de administración puedes:

- **Sincronizar Clientes**: Envía todos los clientes de Bagisto a SIIGO
- **Sincronizar Productos**: Envía todos los productos de Bagisto a SIIGO
- **Probar Conexión**: Verifica que las credenciales son correctas

### API Endpoints

El paquete expone los siguientes endpoints:

```
POST /api/siigo/customers        - Crear cliente en SIIGO
PUT  /api/siigo/customers/{id}   - Actualizar cliente en SIIGO
POST /api/siigo/products         - Crear producto en SIIGO
POST /api/siigo/invoices         - Crear factura en SIIGO
POST /api/siigo/webhook          - Webhook de SIIGO
```

### Webhooks

Para recibir notificaciones de SIIGO, configura el webhook URL en tu cuenta de SIIGO:

```
https://tu-dominio.com/api/siigo/webhook
```

## Estructura del Paquete

```
packages/Polycenter/SIIGO/
├── composer.json                          # Definición del paquete
├── README.md                              # Esta documentación
└── src/
    ├── Config/
    │   └── siigo.php                      # Configuración del paquete
    ├── Database/
    │   └── Migrations/
    │       └── 2024_01_01_000001_create_siigo_mappings_table.php
    ├── Exceptions/
    │   └── SIIGOApiException.php          # Excepciones personalizadas
    ├── Http/
    │   └── Controllers/
    │       ├── Api/
    │       │   └── SIIGOApiController.php # Controlador API
    │       └── SIIGOController.php        # Controlador Admin
    ├── Models/
    │   └── SIIGOMapping.php               # Modelo para mapeos
    ├── Providers/
    │   └── SIIGOServiceProvider.php       # Service Provider
    ├── Resources/
    │   ├── lang/
    │   │   ├── en/
    │   │   │   └── app.php                # Traducciones inglés
    │   │   └── es/
    │   │       └── app.php                # Traducciones español
    │   └── views/
    │       └── admin/
    │           └── index.blade.php        # Vista administrativa
    ├── Routes/
    │   ├── admin.php                      # Rutas administrativas
    │   └── api.php                        # Rutas API
    └── Services/
        └── SIIGOService.php               # Servicio principal de SIIGO
```

## Mapeo de Datos

### Clientes (Bagisto → SIIGO)

- `email` → `email`
- `first_name + last_name` → `name`
- `phone` → `phone`
- Dirección por defecto → `address`

### Productos (Bagisto → SIIGO)

- `sku` → `code`
- `name` → `name`
- `description` → `description`
- `price` → `price`

## Solución de Problemas

### Error de Conexión

1. Verifica que las credenciales sean correctas
2. Asegúrate de que el modo sandbox esté configurado correctamente
3. Revisa los logs en `storage/logs/laravel.log`

### Errores de Sincronización

Los errores se almacenan en la tabla `siigo_mappings` con el estado 'failed'. Puedes consultar:

```sql
SELECT * FROM siigo_mappings WHERE sync_status = 'failed';
```

### Limpiar Caché

```bash
php artisan config:clear
php artisan cache:clear
```

## Desarrollo

### Ejecutar Tests

```bash
php artisan test packages/Polycenter/SIIGO/tests/
```

### Logs de Debug

Los logs se escriben con el prefijo `[SIIGO]` para fácil filtrado:

```bash
tail -f storage/logs/laravel.log | grep SIIGO
```

## Soporte

Para soporte técnico o reportar bugs, contacta a [soporte@polycenter.com](mailto:soporte@polycenter.com).

## Licencia

Este paquete está licenciado bajo la [Licencia MIT](LICENSE).
