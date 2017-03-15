<?php

namespace Google\Cloud\Trace\Sampler;

/**
 * This implementation of the SamplerInterface uses a cache to limit sampling to
 * the a certain number of queries per second.
 */
class QpsSampler implements SamplerInterface
{
    const SAMPLER_CONFIG_CACHE_KEY = '__google_cloud_trace__';

    /**
     * The QPS rate.
     * @var float
     */
    private $rate;

    private $cache;

    public function __construct($options)
    {
        $this->rate = $options['rate'];
        $this->cache = $options['cache'];
    }

    public function shouldSample()
    {
        if ($this->cache()->get(self::SAMPLER_CONFIG_CACHE_KEY)) {
            return false;
        }
        $this->cache()->set(self::SAMPLER_CONFIG_CACHE_KEY, '', $this->nextExpiry());
        return true;
    }

    private function nextExpiry()
    {
        return 1.0 / $this->rate();
    }
}
