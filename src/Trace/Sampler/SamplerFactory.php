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
     * @param array|SamplerInterface $options
     * @return SamplerInterface
     */
    public static function build($options)
    {
        if (is_a($options, SamplerInterface::class)) {
            return $options;
        }

        $options += [
            'type' => 'enabled',
            'rate' => 0.1
        ];

        switch($options['type']) {
            case 'qps':
                $options += [
                    'cache' => null,
                    'cacheItemClass' => null,
                    'cacheKey' => null
                ];
                return new QpsSampler(
                    $options['cache'],
                    $options['cacheItemClass'],
                    $options['rate'],
                    $options['cacheKey']
                );
            case 'random':
                return new RandomSampler($options['rate']);
            case 'enabled':
                return new AlwaysOnSampler();
            case 'disabled':
            default:
                return new AlwaysOffSampler();
        }
    }
}
