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

use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\Sampler\SamplerFactory;
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
     * @var RequestTracer Singleton instance
     */
    private static $instance;

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([self::$instance, '_' . $name], $arguments);
    }

    /**
     * Start a new trace session for this request. You should call this as early as
     * possible for the most accurate results.
     *
     * @param  ReporterInterface $reporter How to report traces at the end of the request
     * @param  array             $options  [description]
     * @return RequestTracer
     */
    public static function start(ReporterInterface $reporter, array $options)
    {
        self::$instance = new static($reporter, $options);
        return self::$instance;
    }

    private $reporter;
    private $tracer;

    protected function __construct(ReporterInterface $reporter, array $options)
    {
        $this->reporter = $reporter;
        $sampler = SamplerFactory::build($options);
        $headers = $this->fetchHeaders($options);

        $context = TraceContext::fromHeaders($headers);

        if ($context->enabled() !== false) {
            $context->setEnabled($context->enabled() || $sampler->shouldSample());
        }
        $this->tracer = $context->enabled()
            ? new ContextTracer($context)
            : new NullTracer();

        $this->tracer->startSpan($options + [
            'name' => $this->nameFromHeaders($headers),
            'labels' => $this->labelsFromHeaders($headers)
        ]);

        register_shutdown_function([$this, 'onExit']);
    }

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
        $this->tracer->finishSpan();
        $this->reporter->report($this->tracer);
    }

    public function tracer()
    {
        return $this->tracer;
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
    public function _instrument(array $spanOptions, callable $callable)
    {
        return $this->tracer->instrument($spanOptions, $callable);
    }

    /**
     * Explicitly start a new TraceSpan. You will need to manage finishing the TraceSpan,
     * including handling any thrown exceptions.
     *
     * @param  [type] $spanOptions [description]
     * @return TraceSpan
     */
    public function _startSpan($spanOptions)
    {
        return $this->tracer->startSpan($spanOptions);
    }

    public function _retroSpan($seconds, $spanOptions)
    {
        $this->_startSpan($spanOptions + ['startTime' => microtime(true) - $seconds]);
        $this->_finishSpan();
    }

    /**
     * Explicitly finish the current context (TraceSpan).
     *
     * @return TraceSpan
     */
    public function _finishSpan()
    {
        return $this->tracer->finishSpan();
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

    private function fetchHeaders($options)
    {
        if (array_key_exists('headers', $options)) {
            return $options['headers'];
        } else {
            return $_SERVER;
        }
    }

    private function nameFromHeaders($headers)
    {
        if (array_key_exists('REQUEST_URI', $headers)) {
            return $headers['REQUEST_URI'];
        }
        return self::DEFAULT_ROOT_SPAN_NAME;
    }

    private function labelsFromHeaders($headers)
    {
        $labels = [];

        $labelMap = [
            self::HTTP_URL => ['REQUEST_URI'],
            self::HTTP_METHOD => ['REQUEST_METHOD'],
            self::HTTP_CLIENT_PROTOCOL => ['SERVER_PROTOCOL'],
            self::HTTP_USER_AGENT => ['HTTP_USER_AGENT'],
            self::HTTP_HOST => ['HTTP_HOST', 'SERVER_NAME'],
            self::GAE_APP_MODULE => ['GAE_SERVICE'],
            self::GAE_APP_VERSION => ['GAE_VERSION']
        ];
        foreach ($labelMap as $labelKey => $headerKeys) {
            $val = array_reduce($headerKeys, function ($carry, $headerKey) use ($headers) {
                return $carry ?: (array_key_exists($headerKey, $headers) ? $headers[$headerKey] : null);
            });
            if ($val) {
                $labels[$labelKey] = $val;
            }
        }
        $labels[self::PID] = '' . getmypid();
        $labels[self::AGENT] = 'google-cloud-php';

        return $labels;
    }
}
