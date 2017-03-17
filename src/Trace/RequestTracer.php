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

use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\Sampler\AlwaysOffSampler;
use Google\Cloud\Trace\Sampler\AlwaysOnSampler;
use Google\Cloud\Trace\Sampler\QpsSampler;
use Google\Cloud\Trace\Sampler\RandomSampler;
use Google\Cloud\Trace\Tracer\ContextTracer;
use Google\Cloud\Trace\Tracer\NullTracer;
use Google\Cloud\Trace\Tracer\TracerInterface;
use Google\Cloud\Trace\Reporter\ReporterInterface;
use Google\Cloud\Trace\TraceSpan;

/**
 * This class provides static fuctions to give you access to the current
 * request's singleton tracer.
 */
class RequestTracer
{
    const HTTP_HEADER = 'HTTP_X_CLOUD_TRACE_CONTEXT';
    const CONTEXT_HEADER_FORMAT = '/([0-9a-f]{32})(?:\/(\d+))?(?:;o=(\d+))?/';
    const DEFAULT_MAIN_SPAN_NAME = 'main';

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
    const HTTP_REQUEST_SIZE = '/http/request/size';
    const HTTP_RESPONSE_SIZE = '/http/response/size';
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
    const GAE_DATASTORE_COUNT = 'g.co/gae/datastore/count';
    const GAE_DATASTORE_CURSOR = 'g.co/gae/datastore/cursor';
    const GAE_DATASTORE_ENTITY_WRITES = 'g.co/gae/datastore/entity_writes';
    const GAE_DATASTORE_HAS_ANCESTOR = 'g.co/gae/datastore/has_ancestor';
    const GAE_DATASTORE_HAS_CURSOR = 'g.co/gae/datastore/has_cursor';
    const GAE_DATASTORE_HAS_TRANSACTION = 'g.co/gae/datastore/has_transaction';
    const GAE_DATASTORE_INDEX_WRITES = 'g.co/gae/datastore/index_writes';
    const GAE_DATASTORE_KIND = 'g.co/gae/datastore/kind';
    const GAE_DATASTORE_LIMIT = 'g.co/gae/datastore/limit';
    const GAE_DATASTORE_MORE_RESULTS = 'g.co/gae/datastore/more_results';
    const GAE_DATASTORE_OFFSET = 'g.co/gae/datastore/offset';
    const GAE_DATASTORE_REQUESTED_ENTITY_DELETES = 'g.co/gae/datastore/requested_entity_deletes';
    const GAE_DATASTORE_REQUESTED_ENTITY_PUTS = 'g.co/gae/datastore/requested_entity_puts';
    const GAE_DATASTORE_SIZE = 'g.co/gae/datastore/size';
    const GAE_DATASTORE_SKIPPED = 'g.co/gae/datastore/skipped';
    const GAE_DATASTORE_TRANSACTION_HANDLE = 'g.co/gae/datastore/transaction_handle';
    const GAE_ERROR_MESSAGE = 'g.co/gae/error_message';
    const GAE_MEMCACHE_COUNT = 'g.co/gae/memcache/count';
    const GAE_MEMCACHE_SIZE = 'g.co/gae/memcache/size';
    const GAE_REQUEST_LOG_ID = 'g.co/gae/request_log_id';

    /**
     * @var TracerInterface
     */
    private static $tracer;

    /**
     * Start a new trace session for this request. You should call this as early as
     * possible for the most accurate results.
     *
     * @param  ReporterInterface $reporter How to report traces at the end of the request
     * @param  array             $options  [description]
     */
    public static function start(TraceClient $client, ReporterInterface $reporter, array $options)
    {
        $sampler = static::samplerFactory($options);
        $headers = static::fetchHeaders($options);
        $context = static::contextFromHeaders($headers);

        $tracer = $sampler->shouldSample()
            ? new ContextTracer($client)
            : new NullTracer();

        $tracer->startSpan($options + $context + [
            'name' => self::DEFAULT_MAIN_SPAN_NAME
        ]);

        register_shutdown_function(function () use ($reporter, $tracer) {
            $responseCode = http_response_code();

            // If a redirect, add the HTTP_REDIRECTED_URL label to the main span
            if ($responseCode == 301 || $responseCode == 302) {
                foreach (headers_list() as $header) {
                    if (substr($header, 0, 9) == "Location:") {
                        $tracer->addLabel(self::HTTP_REDIRECTED_URL, substr($header, 10));
                        break;
                    }
                }
            }

            $tracer->addLabel(self::HTTP_STATUS_CODE, $responseCode);
            $tracer->finishSpan();
            $reporter->report($tracer);
        });

        self::$tracer = $tracer;
    }

    /**
     * Instrument a callable by creating a TraceSpan that manages the startTime and endTime.
     * If an exception is thrown while executing the callable, the exception will be caught,
     * the span will be closed, and the exception will be re-thrown.
     *
     * @param  array    $spanOptions [description]
     * @param  callable $callable    The callable to instrument.
     * @return mixed Returns whatever the callable returns
     */
    public static function instrument(array $spanOptions, callable $callable)
    {
        return self::$tracer->instrument($spanOptions, $callable);
    }

    /**
     * Explicitly start a new TraceSpan. You will need to manage finishing the TraceSpan,
     * including handling any thrown exceptions.
     *
     * @param  [type] $spanOptions [description]
     * @return TraceSpan
     */
    public static function startSpan($spanOptions)
    {
        return self::$tracer->startSpan($spanOptions);
    }

    /**
     * Explicitly finish the current context (TraceSpan).
     *
     * @return TraceSpan
     */
    public static function finishSpan()
    {
        return self::$tracer->finishSpan();
    }

    /**
     * Return the current context (TraceSpan)
     *
     * @return TraceSpan
     */
    public static function context()
    {
        return self::$tracer->context();
    }

    /**
     * Clean up and report the provided TracerInterface using the provided TraceReporterInterface
     *
     * @param  TraceReporterInterface $reporter The trace reporter to use
     * @param  TracerInterface $tracer The tracer to report.
     */
    public static function report(TraceReporterInterface $reporter, TracerInterface $tracer)
    {
        var_dump("here");
        $responseCode = http_response_code();

        // If a redirect, add the HTTP_REDIRECTED_URL label to the main span
        if ($responseCode == 301 || $responseCode == 302) {
            foreach (headers_list() as $header) {
                if (substr($header, 0, 9) == "Location:") {
                    $tracer->addLabel(self::HTTP_REDIRECTED_URL, substr($header, 10));
                    break;
                }
            }
        }

        $tracer->addLabel(self::HTTP_STATUS_CODE, $responseCode);
        $tracer->finishSpan();
        $reporter->report($tracer);
    }

    private static function fetchHeaders($options)
    {
        if (array_key_exists('headers', $options)) {
            return $options['headers'];
        } else {
            return $_SERVER;
        }
    }

    private static function contextFromHeaders($headers)
    {
        $context = [];
        if (array_key_exists(self::HTTP_HEADER, $headers) &&
            preg_match(self::CONTEXT_HEADER_FORMAT, $headers[self::HTTP_HEADER], $matches)) {
            $context += array(
                'traceId' => $matches[1],
                'parentSpanId' => $matches[2],
                'enabled' => array_key_exists(3, $matches) ? $matches[3] == '1' : true
            );
        }
        if (array_key_exists('REQUEST_URI', $headers)) {
            $context['name'] = $headers['REQUEST_URI'];
        }
        $context['labels'] = self::labelsFromHeaders($headers);
        return $context;
    }

    private static function labelsFromHeaders($headers)
    {
        $labels = [];

        $labelMap = [
            self::HTTP_URL => ['REQUEST_URI'],
            self::HTTP_METHOD => ['REQUEST_METHOD'],
            self::HTTP_CLIENT_PROTOCOL => ['SERVER_PROTOCOL'],
            self::HTTP_USER_AGENT => ['HTTP_USER_AGENT'],
            self::HTTP_HOST => ['HTTP_HOST', 'SERVER_NAME']
        ];
        foreach ($labelMap as $labelKey => $headerKeys) {
            $val = array_reduce($headerKeys, function ($carry, $headerKey) use ($headers) {
                return $carry ?: (array_key_exists($headerKey, $headers) ? $headers[$headerKey] : null);
            });
            $labels[$labelKey] = $val;
        }
        $labels[self::PID] = "" . getmypid();

        return $labels;
    }

    private static function samplerFactory($options)
    {
        if (array_key_exists('qps', $options)) {
            return new QpsSampler($options['qps']);
        } elseif (array_key_exists('random', $options)) {
            return new RandomSampler($options['random']);
        } elseif (array_key_exists('enabled', $options) && $options) {
            return new AlwaysOnSampler();
        } else {
            return new AlwaysOffSampler();
        }
    }
}
