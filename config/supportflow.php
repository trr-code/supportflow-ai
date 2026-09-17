<?php

return [

    'brand' => [
        'company' => env('SUPPORTFLOW_COMPANY', 'Harbor & Co'),
        'product' => env('SUPPORTFLOW_PRODUCT', 'Harbor Outfitters'),
        'agent_name' => env('SUPPORTFLOW_AGENT_NAME', 'Alex Rivera'),
        'agent_email' => env('SUPPORTFLOW_AGENT_EMAIL', 'alex.rivera@harborandco.example'),
    ],

    'models' => [
        'triage' => env('OPENAI_TRIAGE_MODEL', 'gpt-5.6-luna'),
        'reply' => env('OPENAI_REPLY_MODEL', 'gpt-5.6-terra'),
        'chat' => env('OPENAI_CHAT_MODEL', 'gpt-5.6-luna'),
        'embeddings' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
    ],

    'embeddings' => [
        'dimensions' => 1536,
    ],

    'retrieval' => [
        'min_similarity' => (float) env('SUPPORTFLOW_MIN_SIMILARITY', 0.45),
        'limit' => 6,
        'candidates' => 12,
        'max_per_article' => 2,
        'high' => 0.72,
        'medium' => 0.58,
    ],

    'pricing' => [
        'as_of' => '2026-08-23',
        'source_url' => 'https://developers.openai.com/api/docs/pricing',
        'disclaimer' => 'Application estimate from configured rates. OpenAI Usage is authoritative.',
        'models' => [
            'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
            'gpt-5.6-terra' => ['input' => 2.00, 'output' => 12.00],
            'gpt-5.6-sol' => ['input' => 4.00, 'output' => 20.00],
            'text-embedding-3-small' => ['input' => 0.02, 'output' => 0.0],
            'gpt-live-transcribe' => ['per_minute' => 0.017],
        ],
    ],

    'dictation' => [
        'model' => 'gpt-live-transcribe',
        'max_seconds' => 120,
        'sessions_per_hour' => 20,
    ],

    'demo' => [
        'stale_minutes' => (int) env('DEMO_STALE_MINUTES', 45),
        'max_tickets_per_session' => 5,
        'max_visitor_tickets' => 50,
        'chat_turn_cap' => 10,
    ],

    'rate_limits' => [
        'regenerate' => [
            'max_attempts' => 5,
            'decay_seconds' => 600,
        ],
    ],

];
