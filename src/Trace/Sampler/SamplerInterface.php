<?php

namespace Google\Cloud\Trace\Sampler;

/**
 * This interface lets us customize the sampling logic.
 */
interface SamplerInterface
{
    /**
     * Returns true if we whould sample the request
     *
     * @return bool
     */
    public function shouldSample();
}
