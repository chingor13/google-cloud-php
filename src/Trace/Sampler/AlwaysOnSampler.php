<?php

namespace Google\Cloud\Trace\Sampler;

/**
 * This implementation of the SamplerInterface always returns false. Use this
 * sampler to attempt to trace all requests. You may be throttled by the server.
 */
class AlwaysOnSampler implements SamplerInterface
{
    /**
     * Returns true because we always want to sample.
     *
     * @return bool
     */
    public function shouldSample()
    {
        return true;
    }
}
