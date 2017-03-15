<?php

namespace Google\Cloud\Trace\Reporter;

use Google\Cloud\Trace\Tracer\TracerInterface;

interface ReporterInterface
{
    /**
     * Report the provided Trace to a backend.
     *
     * @param  TracerInterface $tracer
     * @return bool
     */
    public function report(TracerInterface $tracer);
}
