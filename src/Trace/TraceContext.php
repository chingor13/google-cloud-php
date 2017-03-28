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

namespace Google\Cloud\Trace;

class TraceContext
{
    use IdGeneratorTrait;

    const HTTP_HEADER = 'HTTP_X_CLOUD_TRACE_CONTEXT';
    const CONTEXT_HEADER_FORMAT = '/([0-9a-f]{32})(?:\/(\d+))?(?:;o=(\d+))?/';

    private $traceId;
    private $spanId;
    private $enabled;

    public static function fromHeaders($headers)
    {
        if (array_key_exists(self::HTTP_HEADER, $headers) &&
            preg_match(self::CONTEXT_HEADER_FORMAT, $headers[self::HTTP_HEADER], $matches)) {
            return new static(
                $matches[1],
                array_key_exists(2, $matches) ? $matches[2] : null,
                array_key_exists(3, $matches) ? $matches[3] == '1' : null
            );
        }
        return new static();
    }

    public function __construct($traceId = null, $spanId = null, $enabled = null)
    {
        $this->traceId = $traceId ?: $this->generateTraceId();
        $this->spanId = $spanId;
        $this->enabled = $enabled;
    }

    public function traceId()
    {
        return $this->traceId;
    }

    public function setTraceId($traceId)
    {
        $this->traceId = $traceId;
    }

    public function spanId()
    {
        return $this->spanId;
    }

    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    public function enabled()
    {
        return $this->enabled;
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    public function __toString()
    {
        $ret = '' . $this->traceId;
        if ($this->spanId) {
            $ret .= '/' . $this->spanId;
        }
        $ret .= ';o=' . ($this->enabled ? '1' : '0');
        return $ret;
    }
}
