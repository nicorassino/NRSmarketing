<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NRSMarketing Agent Configuration
    |--------------------------------------------------------------------------
    */

    'analyst' => [
        'model' => env('GEMINI_PRO_MODEL', 'gemini-2.5-pro'),
        'max_tokens' => 8192,
        'temperature' => 0.7,
        'description' => 'Analiza documentación de producto, extrae pain points y genera misión de búsqueda.',
        'system_prompt' => <<<'PROMPT'
Eres un analista senior de marketing B2B especializado en software empresarial. Tu trabajo es:

1. Leer la documentación completa de un producto de software
2. Identificar los PUNTOS DE DOLOR que el producto resuelve
3. Definir la PROPUESTA DE VALOR única
4. Identificar el PERFIL DE CLIENTE IDEAL (ICP)
5. Generar KEYWORDS de búsqueda relevantes
6. Crear una MISIÓN DE BÚSQUEDA detallada para el Agente Scout

Tu análisis debe ser profundo, específico y orientado a la acción comercial.
Responde siempre en español argentino profesional.
PROMPT,
    ],

    'scout' => [
        'model' => env('GEMINI_FLASH_MODEL', 'gemini-2.5-flash'),
        'max_searches' => 10,
        'max_results_per_search' => 20,
        'description' => 'Ejecuta búsquedas web basadas en la misión y encuentra prospectos calificados.',
    ],

    'executor' => [
        'model' => env('GEMINI_FLASH_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => 2048,
        'temperature' => 0.8,
        'batch_size' => 10,
        'delay_between_messages' => 30,
        'channels' => ['whatsapp', 'email', 'instagram'],
        'description' => 'Genera mensajes personalizados y los envía por el canal seleccionado.',
        'system_prompt' => <<<'PROMPT'
Eres un copywriter experto en ventas B2B por mensajería directa.
Tu trabajo es redactar mensajes cortos, personalizados y que generen curiosidad.

Reglas:
- Máximo 3 párrafos cortos
- Mencionar un pain point específico del prospecto
- No ser agresivo ni genérico
- Incluir una pregunta abierta al final
- Adaptar el tono según el canal (WhatsApp más informal, Email más profesional, Instagram más visual)
- Responde siempre en español argentino profesional pero cercano
PROMPT,
    ],

    'chat' => [
        'model' => env('GEMINI_FLASH_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => 4096,
        'temperature' => 0.7,
        'max_context_tokens' => 500000,
        'system_prompt' => <<<'PROMPT'
Eres el Director de Orquesta de NRSMarketing, un sistema de prospección inteligente.
Tenés acceso a todos los archivos de contexto generados por los agentes.

Podés:
- Analizar los resultados de búsqueda y sugerir mejoras
- Modificar la misión de búsqueda del Scout
- Sugerir nuevas estrategias de prospección
- Ayudar a editar y mejorar mensajes
- Reiniciar agentes con nuevas instrucciones

Siempre respondé en español argentino, sé conciso y orientado a la acción.
PROMPT,
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget Control
    |--------------------------------------------------------------------------
    */

    'budget' => [
        'monthly_limit_usd' => (float) env('MONTHLY_BUDGET_USD', 5.00),
        'warning_threshold' => 0.80, // Alertar al 80% del presupuesto
        'critical_threshold' => 0.95, // Bloquear al 95% del presupuesto
    ],

    /*
    |--------------------------------------------------------------------------
    | API Pricing (USD per unit) — Updated periodically
    |--------------------------------------------------------------------------
    */

    'pricing' => [
        'gemini_pro_input_per_1m' => 1.25,
        'gemini_pro_output_per_1m' => 10.00,
        'gemini_flash_input_per_1m' => 0.15,
        'gemini_flash_output_per_1m' => 0.60,
        'serpapi_per_search' => 0.01, // $50/5000 searches
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Files
    |--------------------------------------------------------------------------
    */

    'context' => [
        'base_path' => env('CONTEXT_FILES_PATH', 'context'),
        'steps' => [
            '01_product_analysis' => ['format' => 'md', 'agent' => 'analyst'],
            '02_scout_mission' => ['format' => 'md', 'agent' => 'analyst'],
            '03_search_results' => ['format' => 'json', 'agent' => 'scout'],
            '04_selected_leads' => ['format' => 'json', 'agent' => 'human'],
            '05_execution_log' => ['format' => 'json', 'agent' => 'executor'],
        ],
    ],
];
