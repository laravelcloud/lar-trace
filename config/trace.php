<?php
/**
 * 配置文件
 */
return [
    'trace_enabled' => getenv('TRACE_ENABLED'),
    'trace_endpoint_url' => getenv('TRACE_ENDPOINT_URL'),
    'trace_rate' => getenv('TRACE_RATE'),
    'trace_service_name' => getenv('TRACE_SERVICE_NAME')
];