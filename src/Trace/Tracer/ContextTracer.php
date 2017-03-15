<?php

namespace Google\Cloud\Trace\Tracer;

use Google\Cloud\Trace\Trace;
use Google\Cloud\Trace\TraceSpan;

class ContextTracer implements TracerInterface
{
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

    public static function build($config)
    {
        $projectId = array_key_exists('projectId', $config)
            ? $config['projectId']
            : null;
        $traceId = array_key_exists('traceId', $config)
            ? $config['traceId']
            : null;

        return new static($projectId, $traceId);
    }

    public function __construct($projectId)
    {
        $this->trace = new Trace($projectId);
    }

    /**
     * Instrument a callable by creating a Span that
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

        $span = new TraceSpan($spanOptions);
        array_push($this->spans, $span);
        array_unshift($this->stack, $span);
        $span->setStart();
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
