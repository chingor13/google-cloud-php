<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Trace\Integrations;

class Memcache
{
    public static function load()
    {
        if (!extension_loaded('stackdriver_trace')) {
            return;
        }

        $labelKeys = function ($memcache, $keyOrKeys) {
            $key = is_array($keyOrKeys) ? implode(",", $keyOrKeys) : $keyOrKeys;
            return [
                'labels' => ['key' => $key]
            ];
        };

        stackdriver_trace_method('Memcache', 'get', $labelKeys);
        stackdriver_trace_method('Memcache', 'set', $labelKeys);

        stackdriver_trace_method('Memcache', 'delete', $labelKeys);
        stackdriver_trace_method('Memcache', 'flush');
        stackdriver_trace_method('Memcache', 'replace', $labelKeys);
        stackdriver_trace_method('Memcache', 'increment', $labelKeys);
        stackdriver_trace_method('Memcache', 'decrement', $labelKeys);
    }
}
