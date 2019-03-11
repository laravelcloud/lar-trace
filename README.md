# lar-trace
lar-trace为服务之间调用提供链路追踪

## Laravel Version Compatibility

- Laravel `5.x.x` is supported in the most recent version (`composer require laravelcloud/lar-trace`)

## Installation
### 安装 Laravel 5.x

安装``laravelcloud/lar-trace``包:

```bash
$ composer require laravelcloud/lar-trace
```

在 ``config/app.php`` 中做如下配置

```php
'providers' => array(
    /*
     * Package Service Providers...
     */
	  LaravelCloud\Trace\TraceLaravel\TracingServiceProvider::class,
)
```

创建Trace的配置文件(``config/trace.php``)

```bash
$ php artisan vendor:publish --provider="LaravelCloud\Trace\TraceLaravel\TracingServiceProvider"
```

添加变量至``.env``

```
TRACE_ENABLED=1
TRACE_ENDPOINT_URL=http://127.0.0.1:9411/api/v2/spans
TRACE_RATE=1.0
TRACE_SERVICE_NAME=lar-examples
TRACE_SQL_BINDINGS=false
```

### Lumen 5.x
...


## 链路追踪系统

* [阿里云-链路追踪](https://www.aliyun.com/product/xtrace)
* [zipkin](https://zipkin.io/)

## Contributing

Dependencies are managed through composer:

```
$ composer install
```

Tests can then be run via phpunit:

```
$ vendor/bin/phpunit
```


## Community

* [Bug Tracker](https://github.com/laravelcloud/lar-trace/issues)
* [Code](https://github.com/laravelcloud/lar-trace)

