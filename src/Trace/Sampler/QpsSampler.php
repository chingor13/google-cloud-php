<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Trace\Sampler;

use Google\Auth\Cache\Item;
use Psr\Cache\CacheItemPoolInterface;

/**
 * This implementation of the SamplerInterface uses a cache to limit sampling to
 * the a certain number of queries per second. It requires a PSR-6 cache implementation.
 */
class QpsSampler implements SamplerInterface
{
    const DEFAULT_CACHE_KEY = '__google_cloud_trace__';
    const DEFAULT_QPS_RATE = 0.1;
    const DEFAULT_CACHE_ITEM_CLASS = Item::class;

    /**
     * @var CacheItemPoolInterface The cache store used for storing the last
     */
    private $cache;

    /**
     * @var float The QPS rate.
     */
    private $rate;

    /**
     * @var string The class name of the cache item interface to use
     */
    private $cacheItemClass;

    /**
     * Create a new QpsSampler. If the provided cache is shared between servers,
     * the queries per second will be counted across servers. If the cache is shared
     * between servers and you wish to sample independently on the servers, provide
     * your own cache key that is different on each server.
     *
     * There may be race conditions between simultaneous requests where they may
     * both (all) be sampled.
     *
     * @param CacheItemPoolInterface $cache The cache store to use
     * @param string $cacheItemClass The class of the item to use. This class must implement CacheItemInterface.
     * @param float $rate [optional] The number of queries per second to allow. Must be less than or equal to 1.
     *        **Defaults to** `0.1`
     * @param string $key [optional] The cache key to use. **Defaults to** `__google_cloud_trace__`
     */
    public function __construct(CacheItemPoolInterface $cache, $cacheItemClass = null, $rate = null, $key = null)
    {
        $this->cache = $cache;
        $this->cacheItemClass = $cacheItemClass ?: self::DEFAULT_CACHE_ITEM_CLASS;
        $this->rate = is_null($rate) ? self::DEFAULT_QPS_RATE : $rate;
        $this->key = $key ?: self::DEFAULT_CACHE_KEY;

        if ($this->rate > 1 || $this->rate <= 0) {
            throw new \InvalidArgumentException('QPS sampling rate must be less that 1 query per second');
        }
    }

    /**
     * Returns whether or not the request should be sampled.
     *
     * @return bool
     */
    public function shouldSample()
    {
        // We will store the microtime timestamp in the cache because some
        // cache implementations will not let you use expiry for anything less
        // than 1 minute
        if ($item = $this->cache->getItem($this->key)) {
            if ((float) $item->get() > microtime(true)) {
                return false;
            }
        }

        $item = new $this->cacheItemClass($this->key);
        $item->set(microtime(true) + $this->nextExpiry());

        // TODO: what if the cache fails to save?
        $this->cache->save($item);

        return true;
    }

    private function nextExpiry()
    {
        return 1.0 / $this->rate;
    }
}
