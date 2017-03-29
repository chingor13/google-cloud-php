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

namespace Google\Cloud\Trace\Reporter;

/**
 * The ReporterFactory builds ReporterInterface instances given a variety of
 * configuration options.
 */
class ReporterFactory
{
    /**
     * Builds a sampler given the provided configuration options.
     *
     * @param array|ReporterInterface $options
     * @return ReporterInterface
     */
    public static function build($options)
    {
        if (is_a($options, ReporterInterface::class)) {
            return $options;
        }

        $options += [
            'type' => 'null',
            'level' => null
        ];

        switch($options['type']) {
            case 'async':
                return new AsyncReporter();
            case 'sync':
                return new SyncReporter($options['client']);
            case 'logger':
                return new LoggerReporter(
                    $options['logger'],
                    $options['level']
                );
            case 'file':
                return new FileReporter($options['file']);
            case 'null':
            default:
                return new NullReporter();
        }
    }
}
