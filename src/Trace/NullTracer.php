<?php

namespace Google\Cloud\Trace;

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
     * @return Span
     */
    public function startSpan(array $spanOptions)
    {
        return;
    }

    /**
     * Finish the current context's Span.
     *
     * @return Span The span just finished.
     */
    public function finishSpan()
    {
        return;
    }

    /**
     * Return the current context.
     *
     * @return Span
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
}
