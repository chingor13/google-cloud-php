<?php

namespace Google\Cloud\Trace;

interface TraceReporterInterface
{
    /**
     * Report the provided Trace to a backend.
     *
     * @param  TracerInterface $tracer
     * @return bool
     */
    public function report(TracerInterface $tracer);
}
