# Google PHP Stackdriver Trace

> Idiomatic PHP client for [Stackdriver Trace](https://cloud.google.com/trace/).

* [Homepage](http://googlecloudplatform.github.io/google-cloud-php)
* [API documentation](http://googlecloudplatform.github.io/google-cloud-php/#/docs/cloud-storage/latest/trace/traceclient)

**NOTE:** This repository is part of [Google Cloud PHP](https://github.com/googlecloudplatform/google-cloud-php). Any
support requests, bug reports, or development contributions should be directed to
that project.

## Installation

1. Install with `composer` or add to your `composer.json`.

```
$ composer require google/cloud-trace
```

2. Include and start the labrary as the first action in your application:

```php
use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\Reporter\SyncReporter;

$trace = new TraceClient();
$reporter = new SyncReporter($trace);
RequestTracer::start($reporter);
```
