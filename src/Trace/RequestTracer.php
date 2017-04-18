<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Trace;

use Google\Cloud\Core\ArrayTrait;
use Google\Cloud\Trace\Reporter\ReporterInterface;
use Google\Cloud\Trace\Sampler\SamplerFactory;
use Google\Cloud\Trace\Sampler\SamplerInterface;
use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\TraceSpan;
use Google\Cloud\Trace\Tracer\ContextTracer;
use Google\Cloud\Trace\Tracer\ExtensionTracer;
use Google\Cloud\Trace\Tracer\NullTracer;
use Google\Cloud\Trace\Tracer\TracerInterface;

/**
 * This class provides static functions to give you access to the current
 * request's singleton tracer. You should use this class to instrument your code.
 * The first step, is to configure and start your `RequestTracer`. Calling `start`
 * will collect trace data during your request and report the results at the
 * request using the provided reporter.
 *
 * Example:
 * ```
 * use Google\Cloud\ServiceBuilder;
 * use Google\Cloud\Trace\Reporter\SyncReporter;
 *
 * $builder = new ServiceBuilder();
 * $reporter = new SyncReporter($builder->trace());
 * RequestTracer::start($reporter);
 * ```
 *
 * In the above example, every request is traced. This is not advised as it will
 * add some latency to each request. We provide a sampling mechanism via the
 * `SamplerInterface`. To add a sampler to your request tracer, provide the `sampler`
 * option to your `start` function.
 *
 * Example:
 * ```
 * use Cache\Adapter\Common\CacheItem;
 * use Cache\Adapter\Apcu\ApcuCachePool;
 * use Google\Cloud\Trace\Sampler\QpsSampler;
 *
 * // a PSR-6 cache implementation
 * $cache = new ApcuCachePool();
 * $sampler = new QpsSampler($cache, CacheItem::class, 0.1);
 * RequestTracer::start($reporter, [
 *   'sampler' => $sampler
 * ]);
 * ```
 *
 * The above uses a query-per-second sampler at 0.1 requests/second. The implementation
 * requires a PSR-6 cache. See {@see Google\Cloud\Trace\Sampler\QpsSampler} for more information.
 * You may provide your own implementation of `SamplerInterface` or use one of the provided.
 * You may provide a configuration array for the sampler instead. See
 * {@see Google\Cloud\Trace\Sampler\SamplerFactory::build()} for builder options.
 *
 * Example using configuration:
 * ```
 * use Cache\Adapter\Common\CacheItem;
 * use Cache\Adapter\Apcu\ApcuCachePool;
 *
 * $cache = new ApcuCachePool();
 * RequestTracer::start([
 *   'sampler' => [
 *     'type' => 'qps',
 *     'rate' => 0.1,
 *     'cache' => $cache,
 *     'cacheItemClass' => CacheItem::class
 *   ]
 * ]);
 * ```
 *
 * To trace code, you can use static functions on the `RequestTracer`. To create a `TraceSpan`
 * for a callable, use the `RequestTracer::inSpan` function. The following code creates 1 Trace
 * with 3 nested TraceSpan instances - the root span, the 'outer' span, and the 'inner' span.
 *
 * Example:
 * ```
 * RequestTracer::start();
 * RequestTracer::inSpan(['name' => 'outer'], function () {
 *   // some code
 *   RequestTracer::inSpan(['name' => 'inner'], function () {
 *     // some code
 *   });
 *   // some code
 * });
 * ```
 *
 * You can also start and finish spans independently throughout your code.
 *
 * Example:
 * ```
 * RequestTracer::startSpan(['name' => 'expensive-operation']);
 * // do expensive operation
 * RequestTracer::endSpan();
 * ```
 *
 * It is recommended that you use the `inSpan` method where you can. An uncaught exception between a
 * `startSpan` and `endSpan` may not correctly close spans.
 */
class RequestTracer
{
    use ArrayTrait;

    const DEFAULT_ROOT_SPAN_NAME = 'main';

    const AGENT = '/agent';
    const COMPONENT = '/component';
    const ERROR_MESSAGE = '/error/message';
    const ERROR_NAME = '/error/name';
    const HTTP_CLIENT_CITY = '/http/client_city';
    const HTTP_CLIENT_COUNTRY = '/http/client_country';
    const HTTP_CLIENT_PROTOCOL = '/http/client_protocol';
    const HTTP_CLIENT_REGION = '/http/client_region';
    const HTTP_HOST = '/http/host';
    const HTTP_METHOD = '/http/method';
    const HTTP_REDIRECTED_URL = '/http/redirected_url';
    const HTTP_STATUS_CODE = '/http/status_code';
    const HTTP_URL = '/http/url';
    const HTTP_USER_AGENT = '/http/user_agent';
    const PID = '/pid';
    const STACKTRACE = '/stacktrace';
    const TID = '/tid';

    const GAE_APPLICATION_ERROR = 'g.co/gae/application_error';
    const GAE_APP_MODULE = 'g.co/gae/app/module';
    const GAE_APP_MODULE_VERSION = 'g.co/gae/app/module_version';
    const GAE_APP_VERSION = 'g.co/gae/app/version';

    /**
     * @var RequestTracer Singleton instance
     */
    private static $instance;

    /**
     * @var ReporterInterface The reported to use at the end of the request
     */
    private $reporter;

    /**
     * @var TracerInterface The tracer to use for this request
     */
    private $tracer;

    /**
     * Forward static calls on `RequestTracer` to the singleton instance.
     *
     * @param string $name Method name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([self::$instance, '_' . $name], $arguments);
    }

    /**
     * Start a new trace session for this request. You should call this as early as
     * possible for the most accurate results.
     *
     * @param ReporterInterface $reporter
     * @param array $options {
     *      Configuration options. See
     *      {@see Google\Cloud\Trace\TraceSpan::__construct()} for the other available options.
     *
     *      @type SamplerInterface|array $sampler Sampler or sampler factory build arguments. See
     *          {@see Google\Cloud\Trace\Sampler\SamplerFactory::build()} for the available options.
     *      @type array $headers Optional array of headers to use in place of $_SERVER
     * }
     * @return RequestTracer
     */
    public static function start(ReporterInterface $reporter, array $options = [])
    {
        $samplerOptions = array_key_exists('sampler', $options) ? $options['sampler'] : [];
        unset($options['sampler']);
        $sampler = SamplerFactory::build($samplerOptions);

        return self::$instance = new static($reporter, $sampler, $options);
    }

    /**
     * Create a new RequestTracer and start tracing this request.
     *
     * @param ReporterInterface $reporter How to report the trace at the end of the request
     * @param SamplerInterface $sampler Which sampler to use for sampling requests
     * @param array $options [optional] {
     *      Configuration options. See
     *      {@see Google\Cloud\Trace\TraceSpan::__construct()} for the other available options.
     *
     *      @type array $headers Optional array of headers to use in place of $_SERVER
     * }
     */
    public function __construct(ReporterInterface $reporter, SamplerInterface $sampler, array $options = [])
    {
        $this->reporter = $reporter;
        $headers = $this->pluck('headers', $options, false) ?: $_SERVER;
        $context = TraceContext::fromHeaders($headers);

        // If the context force disables tracing, don't consult the $sampler.
        if ($context->enabled() !== false) {
            $context->setEnabled($context->enabled() || $sampler->shouldSample());
        }

        // If the request was provided with a trace context header, we need to send it back with the response
        // including whether the request was sampled or not.
        if ($context->fromHeader()) {
            $this->persistContextHeader($context);
        }

        $this->tracer = $context->enabled()
            ? extension_loaded('stackdriver') ? new ExtensionTracer($context) : new ContextTracer($context)
            : new NullTracer();

        $spanOptions = $options + [
            'startTime' => $this->startTimeFromHeaders($headers),
            'name' => $this->nameFromHeaders($headers),
            'labels' => []
        ];
        $spanOptions['labels'] += $this->labelsFromHeaders($headers);
        $this->tracer->startSpan($spanOptions);

        register_shutdown_function([$this, 'onExit']);
    }

    /**
     * The function registered as the shutdown function. Cleans up the trace and reports using the
     * provided ReporterInterface. Adds additional labels to the root span detected from the response.
     */
    public function onExit()
    {
        $responseCode = http_response_code();

        // If a redirect, add the HTTP_REDIRECTED_URL label to the main span
        if ($responseCode == 301 || $responseCode == 302) {
            foreach (headers_list() as $header) {
                if (substr($header, 0, 9) == "Location:") {
                    $this->tracer->addLabel(self::HTTP_REDIRECTED_URL, substr($header, 10));
                    break;
                }
            }
        }

        $this->tracer->addLabel(self::HTTP_STATUS_CODE, $responseCode);

        // close all open spans
        do {
            $span = $this->tracer->endSpan();
        } while ($span);
        $this->reporter->report($this->tracer);
    }

    /**
     * Return the tracer used for this request.
     *
     * @return TracerInterface
     */
    public function tracer()
    {
        return $this->tracer;
    }

    /**
     * inSpan a callable by creating a TraceSpan that manages the startTime and endTime.
     * If an exception is thrown while executing the callable, the exception will be caught,
     * the span will be closed, and the exception will be re-thrown.
     *
     * Example:
     * ```
     * RequestTracer::inSpan(['name' => 'expensive-operation'], function () {
     *   // do something expensive
     * });
     *
     * function fib($n) {
     *   // do something expensive
     * }
     * $number = RequestTracer::inSpan(['name' => 'fibonacci'], 'fib', [10]);
     * ```
     *
     * @param array $spanOptions Options for the span.
     *      {@see Google\Cloud\Trace\TraceSpan::__construct()}
     * @param  callable $callable    The callable to inSpan.
     * @return mixed Returns whatever the callable returns
     */
    public function _inSpan(array $spanOptions, callable $callable, array $arguments = [])
    {
        return $this->tracer->inSpan($spanOptions, $callable, $arguments);
    }

    /**
     * Explicitly start a new TraceSpan. You will need to manage finishing the TraceSpan,
     * including handling any thrown exceptions.
     *
     * Example:
     * ```
     * RequestTracer::startSpan(['name'= > 'expensive-operation']);
     * // do something expensive
     * RequestTracer::endSpan();
     * ```
     *
     * @param array $spanOptions [optional] Options for the span.
     *      {@see Google\Cloud\Trace\TraceSpan::__construct()}
     * @return TraceSpan
     */
    public function _startSpan(array $spanOptions = [])
    {
        return $this->tracer->startSpan($spanOptions);
    }

    /**
     * Explicitly finish the current context (TraceSpan).
     *
     * @return TraceSpan
     */
    public function _endSpan()
    {
        return $this->tracer->endSpan();
    }

    /**
     * Return the current context (TraceSpan)
     *
     * @return TraceContext
     */
    public function _context()
    {
        return $this->tracer->context();
    }

    private function startTimeFromHeaders(array $headers)
    {
        return $this->detectKey(['REQUEST_TIME_FLOAT', 'REQUEST_TIME'], $headers);
    }

    private function nameFromHeaders(array $headers)
    {
        if (array_key_exists('REQUEST_URI', $headers)) {
            return $headers['REQUEST_URI'];
        }
        return self::DEFAULT_ROOT_SPAN_NAME;
    }

    private function labelsFromHeaders(array $headers)
    {
        $labels = [];

        $labelMap = [
            self::HTTP_URL => ['REQUEST_URI'],
            self::HTTP_METHOD => ['REQUEST_METHOD'],
            self::HTTP_CLIENT_PROTOCOL => ['SERVER_PROTOCOL'],
            self::HTTP_USER_AGENT => ['HTTP_USER_AGENT'],
            self::HTTP_HOST => ['HTTP_HOST', 'SERVER_NAME'],
            self::GAE_APP_MODULE => ['GAE_SERVICE'],
            self::GAE_APP_MODULE_VERSION => ['GAE_VERSION'],
            self::HTTP_CLIENT_CITY => ['HTTP_X_APPENGINE_CITY'],
            self::HTTP_CLIENT_REGION => ['HTTP_X_APPENGINE_REGION'],
            self::HTTP_CLIENT_COUNTRY => ['HTTP_X_APPENGINE_COUNTRY']
        ];
        foreach ($labelMap as $labelKey => $headerKeys) {
            if ($val = $this->detectKey($headerKeys, $headers)) {
                $labels[$labelKey] = $val;
            }
        }

        $labels[self::PID] = '' . getmypid();
        $labels[self::AGENT] = 'google-cloud-php ' . TraceClient::VERSION;

        return $labels;
    }

    private function detectKey(array $keys, array $array)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }
        return null;
    }

    private function persistContextHeader($context) {
        if (!headers_sent()) {
            header('X-Cloud-Trace-Context: ' . $context);
        }
    }
}
