# Stackdriver PHP Extension

This extension enables the following services:

* [Stackdriver Trace](https://cloud.google.com/trace/)

Stackdriver Trace is a free, open-source distributed tracing implementation
based on the [Dapper Paper](https://research.google.com/pubs/pub36356.html).
This extension allows you to "watch" class methd and function calls in order to
automatically collect nested spans (labelled timing data).

This library can work in conjunction with the PHP library
[google/cloud-trace](https://packagist.org/packages/google/cloud-trace) in order
to send collected span data to a backend storage server.

This extension also maintains the current trace span context - the current span
the code is currently executing within. Whenever a span is created, it's parent
is set the the current span, and this new span becomes the current trace span
context.

## Compatibilty

This extension has been built and tested on the following PHP versions:

* 7.1.x
* 7.0.x

## Installation

### Build from source

1. [Download a release](https://github.com/GoogleCloudPlatform/google-cloud-php-trace/releases)

   ```bash
   curl https://github.com/GoogleCloudPlatform/google-cloud-php-trace/archive/v0.1.0.tar.gz -o trace.tar.gz
   ```

1. Untar the package

   ```bash
   tar -zxvf trace.tar.gz
   ```

1. Go to the extension directory

   ```bash
   cd google-cloud-php-trace-0.1.0
   ```

1. Compile the extension

   ```bash
   phpize
   configure --enable-stackdriver-trace
   make
   make test
   make install
   ```

1. Enable the stackdriver trace extension. Add the following to your `php.ini` configuration file.

   ```
   extension=stackdriver_trace.so
   ```

### Download from PECL (not yet available)

When this extension is available on PECL, you will be able to download and install it easily using the
`pecl` CLI tool:

```bash
pecl install stackdriver_trace
```

## Usage

### Trace a class method

Whenever the class' method is called, create a trace span.

```php
stackdriver_trace_method(Foobar::class, '__construct');
```

You can specify the span data to use:

```php
stackdriver_trace_method(Foobar::class, '__construct', [
  'name' => 'Foobar::__construct',
  'labels' => [
    'foo' => 'bar'
  ]
]);
```

You can also provide a Closure as a callback in order specify the span data used
for the created span.

```php
stackdriver_trace_method(Foobar::class, '__construct', function () {
  return [
    'name' => 'Foobar::__construct',
    'labels' => [
      'foo' => 'bar'
    ]
  ];
});
```

Additionally, any parameters available to the function will be provided to the Closure.

```php
stackdriver_trace_method(Foobar::class, '__construct', function ($name) {
  return [
    'name' => 'Foobar::__construct',
    'labels' => [
      'name' => $name
    ]
  ];
});

// a new span is created with the label name => 'Bob'
$foobar = new Foobar('Bob');
```

### Trace a function

Whenever the function is called, create a trace span.

```php
stackdriver_trace_function('var_dump');
```

You can specify the span data to use:

```php
stackdriver_trace_function('var_dump', [
  'name' => 'var_dump',
  'labels' => [
    'foo' => 'bar'
  ]
]);
```

You can also provide a Closure as a callback in order specify the span data used
for the created span.

```php
stackdriver_trace_function('var_dump', function () {
  return [
    'name' => 'Foobar::__construct',
    'labels' => [
      'foo' => 'bar'
    ]
  ];
});
```

### List spans

Retrieve an array of collected spans. This returns an array of `Stackdriver\Trace\Span`
instances.

```php
$spans = stackdriver_trace_list();
var_dump($spans);
```

### Get current trace context. This returns a `Stackdriver\Trace\Context` instance.

```php
$context = stackdriver_trace_context();
var_dump($context);
```

### Start a span

```php
stackdriver_trace_start_span($spanOptions);
```

### Finish a span

```php
stackdriver_trace_finish_span($spanOptions);
```
