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
 * The SamplerFactory builds SamplerInterface instances given a variety of
 * configuration options.
 */
class SamplerFactory
{
    /**
     * Builds a sampler given the provided configuration options.
     *
     * @param  array  $options [optional]
     * @return SamplerInterface
     */
    public static function build(array $options = [])
    {
        if (array_key_exists('qps', $options)) {
            $qps = $options['qps'] + [
                'cacheItemClass' => null,
                'rate' => null,
                'key' => null
            ];
            return new QpsSampler($qps['cache'], $qps['cacheItemClass'], $qps['rate'], $qps['key']);
        } elseif (array_key_exists('random', $options)) {
            return new RandomSampler($options['random']);
        } elseif (array_key_exists('enabled', $options) && $options['enabled']) {
            return new AlwaysOnSampler();
        } else {
            return new AlwaysOffSampler();
        }
    }
}
