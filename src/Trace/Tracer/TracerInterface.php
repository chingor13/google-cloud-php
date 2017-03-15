<?php

namespace Google\Cloud\Trace\Tracer;

use Google\Cloud\Trace\Trace;
use Google\Cloud\Trace\TraceSpan;

interface TracerInterface
{
    /**
     * Instrument a callable by creating a Span that
     *
     * @param  array    $spanOptions [description]
     * @param  callable $callable    The callable to instrument.
     * @return mixed The result of the callable
     */
    public function instrument(array $spanOptions, callable $callable);

    /**
     * Start a new Span. The start time is already set to the current time.
     *
     * @param  array  $spanOptions [description]
     * @return TraceSpan
     */
    public function startSpan(array $spanOptions);

    /**
     * Finish the current context's Span.
     *
     * @return TraceSpan The span just finished.
     */
    public function finishSpan();

    /**
     * Return the current context.
     *
     * @return TraceSpan
     */
    public function context();

    /**
     * Return the current trace.
     *
     * @return Trace
     */
    public function trace();
}
