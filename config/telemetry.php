<?php

declare(strict_types=1);

return [
    /**
     * Controla se a auto instrumentação do OpenTelemetry deve ser habilitada.
     */
    'enabled' => (bool) env('OTEL_ENABLED', false),

    /**
     * Nome do serviço reportado ao coletor.
     */
    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'laravel-app')),

    /**
     * Atributos adicionais do recurso OTEL (formato chave=valor, separados por vírgula).
     */
    'resource_attributes' => env('OTEL_RESOURCE_ATTRIBUTES'),

    /**
     * Configurações gerais do exportador OTLP.
     */
    'exporter' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318/v1/traces'),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/json'),
        'headers' => env('OTEL_EXPORTER_OTLP_HEADERS'),
        'metrics_endpoint' => env('OTEL_EXPORTER_OTLP_METRICS_ENDPOINT', 'http://otel-collector:4318/v1/metrics'),
        'metrics_protocol' => env('OTEL_EXPORTER_OTLP_METRICS_PROTOCOL', env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/json')),
        'metrics_headers' => env('OTEL_EXPORTER_OTLP_METRICS_HEADERS', env('OTEL_EXPORTER_OTLP_HEADERS')),
        'logs_endpoint' => env('OTEL_EXPORTER_OTLP_LOGS_ENDPOINT', 'http://otel-collector:4318/v1/logs'),
        'logs_protocol' => env('OTEL_EXPORTER_OTLP_LOGS_PROTOCOL', env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/json')),
        'logs_headers' => env('OTEL_EXPORTER_OTLP_LOGS_HEADERS', env('OTEL_EXPORTER_OTLP_HEADERS')),
    ],

    /**
     * Listagem opcional de URLs a serem ignoradas pela instrumentação automática.
     */
    'excluded_urls' => env('OTEL_PHP_EXCLUDED_URLS'),

    /**
     * Exportadores habilitados para traces, métricas e logs.
     */
    'exporters' => [
        'traces' => env('OTEL_TRACES_EXPORTER', 'otlp'),
        'metrics' => env('OTEL_METRICS_EXPORTER', 'otlp'),
        'logs' => env('OTEL_LOGS_EXPORTER', 'otlp'),
    ],
];
