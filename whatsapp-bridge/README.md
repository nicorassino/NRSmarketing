# WhatsApp Bridge (interno)

Microservicio Node para envio de WhatsApp desde Laravel sin usar Cloud API oficial.

## Estado actual

- API HTTP interna lista con `whatsapp-web.js`.
- Seguridad por token Bearer.
- Flujo de sesion (QR/start/status) y envio real de mensajes.

## Uso rapido

1. Instalar dependencias:
   - `npm install`
2. Definir variables:
   - `WHATSAPP_BRIDGE_TOKEN=tu_token`
   - `WHATSAPP_BRIDGE_PORT=3000` (opcional)
   - `WHATSAPP_SESSION_NAME=nrsmarketing` (opcional)
3. Iniciar:
   - `npm start`

## Endpoints

- `GET /health` (sin auth): estado basico
- `POST /session/start` (auth): inicia cliente/recupera sesion
- `GET /session/status` (auth): estado de sesion
- `GET /session/qr` (auth): QR en data-url (si aplica)
- `POST /send` (auth): envia mensaje `{ to, text, external_id? }`

## Integracion con Laravel

Variables en `.env` de Laravel:

- `WHATSAPP_BRIDGE_URL=http://127.0.0.1:3000`
- `WHATSAPP_BRIDGE_TOKEN=tu_token`
- `WHATSAPP_BRIDGE_TIMEOUT=15`

Laravel usa `App\Services\Messaging\WhatsAppBridgeMessenger`.

## Operacion recomendada

1. Levantar bridge.
2. Llamar `POST /session/start`.
3. Consultar `GET /session/status` y escanear QR de `GET /session/qr` hasta `status=ready`.
4. Ejecutar envios desde NRSMarketing.

## Error "No LID for user" (WhatsApp Web)

WhatsApp migro parte de los identificadores internos (LID). Si falla el envio:

1. **Reiniciá el bridge** tras `npm install` (actualizar `whatsapp-web.js`); el endpoint `/send` resuelve el numero con `getNumberId` antes de enviar.
2. **Abrí una vez el chat** con ese numero en la misma sesion de WhatsApp Web que usa el bridge (o `wa.me/<numero>` en Chrome con esa sesion), y volve a enviar desde NRSMarketing.
3. Verificá el **formato internacional** del telefono en el prospecto (solo digitos, sin `+`; ej. Argentina movil: `54911...` con 9, sin 0 del 011).
