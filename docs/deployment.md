# Deployment notes

## Requisitos

- PHP 8.3+
- MySQL 8+
- Node 20+ (solo para `whatsapp-bridge`)
- Proceso de colas Laravel activo (`queue:work`)

## Pasos base

1. `composer install`
2. `cp .env.example .env`
3. Configurar DB, Gemini, SerpAPI, Mail y WhatsApp bridge
4. `php artisan key:generate`
5. `php artisan migrate --force`
6. `php artisan queue:work --queue=agents,default`

## WhatsApp bridge

1. `cd whatsapp-bridge`
2. `npm install`
3. Definir `WHATSAPP_BRIDGE_TOKEN`
4. `npm start`
5. En app: abrir `WhatsApp` en menu y ejecutar `Iniciar / reconectar`

## Variables importantes

- `GEMINI_API_KEY`
- `SERPAPI_KEY`
- `WHATSAPP_BRIDGE_URL`
- `WHATSAPP_BRIDGE_TOKEN`
- `WHATSAPP_BRIDGE_TIMEOUT`
