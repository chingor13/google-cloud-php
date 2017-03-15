<?php

namespace Google\Cloud\Trace\Tracer;

use Google\Cloud\Trace\Trace;
use Google\Cloud\Trace\TraceSpan;

class NullTracer implements TracerInterface
{
    /**
     * Instrument a callable by creating a Span that
     *
     * @param  array    $spanOptions [description]
     * @param  callable $callable    The callable to instrument.
     * @return mixed The result of the callable
     */
    public function instrument(array $spanOptions, callable $callable)
    {
        return call_user_func($callable);
    }

    /**
     * Start a new Span. The start time is already set to the current time.
     *
     * @param  array  $spanOptions [description]
     * @return TraceSpan
     */
    public function startSpan(array $spanOptions)
    {
        return;
    }

    /**
     * Finish the current context's Span.
     *
     * @return TraceSpan The span just finished.
     */
    public function finishSpan()
    {
        return;
    }

    /**
     * Return the current context.
     *
     * @return TraceSpan
     */
    public function context()
    {
        return null;
    }

    /**
     * Return the constructed Trace
     *
     * @return Trace
     */
    public function trace()
    {
        return null;
    }

    /**
     * Add a label to the primary TraceSpan
     *
     * @param string $label
     * @param string $value
     */
    public function addLabel($label, $value)
    {
    }
}
