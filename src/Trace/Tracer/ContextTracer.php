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

namespace Google\Cloud\Trace\Tracer;

use Google\Cloud\Core\ArrayTrait;
use Google\Cloud\Trace\Trace;
use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\TraceSpan;

/**
 * This implementation of the TracerInterface manages your trace context throughout
 * the request. It maintains a stack of `TraceSpan` records that are currently open
 * allowing you to know the current context at any moment.
 */
class ContextTracer implements TracerInterface
{
    use ArrayTrait;

    /**
     * @var Trace
     */
    private $trace;

    /**
     * List of Spans to report
     * @var TraceSpan[]
     */
    private $spans = [];

    /**
     * Stack of Spans that maintain our nested call stack.
     * @var TraceSpan[]
     */
    private $stack = [];

    /**
     * Create a new trace context.
     *
     * @param string $projectId
     */
    public function __construct(TraceClient $traceClient)
    {
        $this->trace = $traceClient->trace();
    }

    /**
     * Instrument a callable by creating a Span that manages the startTime and endTime.
     *
     * @param  array    $spanOptions [description]
     * @param  callable $callable    The callable to instrument.
     * @return mixed The result of the callable
     */
    public function instrument(array $spanOptions, callable $callable)
    {
        $this->startSpan($spanOptions);
        try {
            return call_user_func($callable);
        } finally {
            $this->finishSpan();
        }
    }

    /**
     * Start a new Span. The start time is already set to the current time.
     *
     * @param  array  $spanOptions [description]
     * @return TraceSpan
     */
    public function startSpan(array $spanOptions)
    {
        if (!array_key_exists('parentSpanId', $spanOptions)) {
            $spanOptions['parentSpanId'] = $this->context()
                ? $this->context()->spanId()
                : null;
        }
        $time = $this->pluck('startTime', $spanOptions, false);
        if ($time) {
            $micro = sprintf("%06d",($time - floor($time)) * 1000000);
            $time = new \DateTime(date('Y-m-d H:i:s.'. $micro, $time));
        }

        $span = new TraceSpan($spanOptions);
        array_push($this->spans, $span);
        array_unshift($this->stack, $span);
        $span->setStart($time);
        return $span;
    }

    /**
     * Finish the current context's Span.
     *
     * @return TraceSpan The span just finished.
     */
    public function finishSpan()
    {
        $span = array_shift($this->stack);
        if ($span) {
            $span->setFinish();
        }
        return $span;
    }

    /**
     * Return the current context.
     *
     * @return TraceSpan
     */
    public function context()
    {
        return empty($this->stack) ? null : $this->stack[0];
    }

    /**
     * Return the constructed Trace
     *
     * @return Trace
     */
    public function trace()
    {
        $this->trace->setSpans($this->spans);
        return $this->trace;
    }

    /**
     * Add a label to the primary TraceSpan
     *
     * @param string $label
     * @param string $value
     */
    public function addLabel($label, $value)
    {
        if (!empty($this->spans)) {
            $this->spans[0]->addLabel($label, $value);
        }
    }
}
