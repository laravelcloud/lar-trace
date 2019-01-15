<?php
/**
 * 配置文件
 */
return [
    'enabled' => getenv('TRACE_ENABLED'),
    'endpoint_url' => getenv('TRACE_ENDPOINT_URL'),
    'rate' => getenv('TRACE_RATE'),
    'service_name' => getenv('TRACE_SERVICE_NAME'),
    'sql_bindings' => getenv('TRACE_SQL_BINDINGS')
];


