<?php

namespace Google\Cloud\Trace;

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
     * @return Span
     */
    public function startSpan(array $spanOptions);

    /**
     * Finish the current context's Span.
     *
     * @return Span The span just finished.
     */
    public function finishSpan();

    /**
     * Return the current context.
     *
     * @return Span
     */
    public function context();

    /**
     * Return the current trace.
     *
     * @return Trace
     */
    public function trace();
}
