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
use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\TraceSpan;
use Google\Cloud\Trace\TraceContext;

/**
 * This implementation of the TracerInterface manages your trace context throughout
 * the request. It maintains a stack of `TraceSpan` records that are currently open
 * allowing you to know the current context at any moment.
 */
class ContextTracer implements TracerInterface
{
    use ArrayTrait;

    /**
     * @var TraceSpan[] List of Spans to report
     */
    private $spans = [];

    /**
     * @var TraceSpan[] Stack of Spans that maintain our nested call stack.
     */
    private $stack = [];

    /**
     * @var TraceContext The current context of this tracer.
     */
    private $context;

    public function __construct(TraceContext $context = null)
    {
        $this->context = $context ?: new TraceContext();
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
        $spanOptions += [
            'parentSpanId' => $this->context()->spanId()
        ];

        $span = new TraceSpan($spanOptions);
        array_push($this->spans, $span);
        array_unshift($this->stack, $span);
        $this->context->setSpanId($span->spanId());
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
        $this->context->setSpanId(empty($this->stack) ? null : $this->stack[0]->spanId());
        if ($span) {
            $span->setFinish();
        }
        return $span;
    }

    /**
     * Return the current context.
     *
     * @return TraceContext
     */
    public function context()
    {
        return $this->context;
    }

    /**
     * Return the spans collected.
     *
     * @return TraceSpan[]
     */
    public function spans()
    {
        return $this->spans;
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

    /**
     * Whether or not this tracer is enabled.
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->context->enabled();
    }
}
