# Localhost WAMP quickstart

## 1) Preparacion

1. Iniciar WAMP (Apache + MySQL).
2. Crear DB en MySQL: `nrsmarketing`.
3. Desde PowerShell:
   - `cd C:\wamp64\www\NRSmarketing-1`
   - `.\scripts\setup-local.ps1`

## 2) Arranque diario

- `.\scripts\start-local.ps1`

Esto abre 3 consolas:

- Laravel app (`php artisan serve`)
- Vite (`npm run dev`)
- WhatsApp bridge (`npm start` en `whatsapp-bridge`)

## 3) Primer uso

1. Entrar a `http://127.0.0.1:8000`
2. Login/registro
3. Ir a menu `WhatsApp` y presionar `Iniciar / reconectar`
4. Escanear QR
5. Crear `Productos`, subir docs, ejecutar `Analyst`
6. Crear `Campanas`, ejecutar `Scout`, revisar `Inbox`, ejecutar `Executor`

## 4) Notas

- En local, `QUEUE_CONNECTION=sync` para simplificar (sin worker separado).
- Si queres colas reales en local:
  - cambiar `QUEUE_CONNECTION=database`
  - correr `php artisan queue:work --queue=agents,default`
- Logo de marca:
  - copiar `logoNR.png` a `public/images/logo-nr.png`
