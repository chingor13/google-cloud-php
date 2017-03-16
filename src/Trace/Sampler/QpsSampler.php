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
