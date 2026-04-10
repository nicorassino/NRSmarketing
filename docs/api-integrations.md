# API Integrations

## Gemini

- Servicio: `app/Services/AI/GeminiService.php`
- Uso:
  - Agentes (`generate`)
  - Chat por producto (`chat`)
- Log de consumo: `api_usage_logs`

## SerpAPI

- Servicio: `app/Services/Search/SerpApiService.php`
- Consumido por Scout Agent.

## WhatsApp no oficial

- Bridge interno Node en `whatsapp-bridge/`
- Transporte Laravel: `app/Services/Messaging/WhatsAppBridgeMessenger.php`
- Endpoint principal bridge: `POST /send`

## Email SMTP

- Transporte Laravel: `app/Services/Messaging/EmailMessenger.php`
- Configuracion en `.env` (`MAIL_*`)

## Instagram

- Transporte base: `app/Services/Messaging/InstagramMessenger.php`
- Actualmente pendiente de API real.
