<?php

namespace Google\Cloud\Trace\Sampler;

/**
 * This implementation of the SamplerInterface uses a pseudo-random number generator
 * to sample a percentage of requests.
 */
class RandomSampler implements SamplerInterface
{
    /**
     * The percentage of requests to sample represented as a float between 0 and 1.
     * @var float
     */
    private $percentage;

    /**
     * Creates the RandomSampler
     *
     * @param float $percentage
     */
    public function __construct(float $percentage)
    {
        $this->percentage = $percentage;
    }

    /**
     * Uses a pseudo-random number generator to decide if we should sampel the request.
     *
     * @return bool
     */
    public function shouldSample()
    {
        return lcg_value() <= $this->percentage;
    }
}
