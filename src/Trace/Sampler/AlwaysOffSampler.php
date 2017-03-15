<?php

namespace Google\Cloud\Trace\Sampler;

/**
 * This implementation of the SamplerInterface always returns false. Use this
 * sampler to disable all tracing.
 */
class AlwaysOffSampler implements SamplerInterface
{
    /**
     * Returns false because we never want to sample.
     *
     * @return bool
     */
    public function shouldSample()
    {
        return false;
    }
}
