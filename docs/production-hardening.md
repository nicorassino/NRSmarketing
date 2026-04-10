# Production hardening checklist

## 1) App Laravel

- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true` (si hay HTTPS)
- `QUEUE_CONNECTION=database` o `redis`
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## 2) Database

- Backups diarios
- Usuario DB con permisos minimos
- Monitoreo de crecimiento de tablas `chat_messages` y `api_usage_logs`

## 3) Queue workers

- Ejecutar worker permanente via supervisor:
  - ver `deploy/supervisor/laravel-worker.conf`
- Revisar logs de worker
- Configurar rotacion de logs

## 4) WhatsApp bridge

- Correr solo en red interna o `127.0.0.1`
- No exponer puerto 3000 a internet
- Token fuerte en `WHATSAPP_BRIDGE_TOKEN`
- Configurar supervisor/systemd:
  - `deploy/supervisor/whatsapp-bridge.conf`
  - o `deploy/systemd/nrsmarketing-whatsapp-bridge.service`
- Verificar estado en pantalla `WhatsApp` de la app

## 5) Seguridad

- HTTPS obligatorio en app
- Limitar acceso SSH por IP/keys
- Actualizaciones del sistema al dia
- Revisar permisos de `storage/` y `bootstrap/cache/`

## 6) Operacion diaria

- Validar bridge: `status=ready`
- Verificar queue worker activo
- Revisar consumo API en Dashboard
- Confirmar envios de prueba en run controlado
