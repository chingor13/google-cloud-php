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

namespace Google\Cloud\Core\Context;

use Google\Cloud\Core\IdGeneratorTrait;

/**
 * TraceContext encapsulates your current context within your request's trace. It includes
 * 3 fields: the `traceId`, the current `spanId`, and an `enabled` flag which indicates whether
 * or not the request is being traced.
 *
 * Example:
 *
 * ```
 * use Google\Cloud\Trace\RequestTracer;
 *
 * $context = RequestTracer::context();
 * echo $context; // output the header format for using the current context in a remote call
 * ```
 */
class TraceContext
{
    use IdGeneratorTrait;

    /**
     * @var string The current traceId.
     */
    private $traceId;

    /**
     * @var string The current spanId. This is the deepest nested span currently open.
     */
    private $spanId;

    /**
     * @var bool Whether or not tracing is enabled for this request.
     */
    private $enabled;

    /**
     * @var bool Whether or not this context came from a HTTP header.
     */
    private $fromHeader;

    public static function fromContext(Context $context = null)
    {
        $context = $context ?: Context::current();
        return new static(
            $context->value('traceId'),
            $context->value('spanId'),
            $context->value('traceEnabled'),
            $context->value('traceSampledFromHeader')
        );
    }

    /**
     * Creates a new TraceContext instance
     *
     * @param string $traceId The current traceId. If not set, one will be generated for you.
     * @param string $spanId The current spanId
     * @param bool $enabled Whether or not tracing is enabled on this request **Defaults to** `null`.
     * @param bool $fromHeader Whether or not the context was detected from an incoming header. **Defaults to** `false`.
     */
    public function __construct($traceId = null, $spanId = null, $enabled = null, $fromHeader = false)
    {
        $this->traceId = $traceId ?: $this->generateTraceId();
        $this->spanId = $spanId;
        $this->enabled = $enabled;
        $this->fromHeader = !!$fromHeader;
    }

    /**
     * Fetch the current traceId.
     *
     * @return string
     */
    public function traceId()
    {
        return $this->traceId;
    }

    /**
     * Set the current traceId.
     *
     * @param string $traceId The traceId to set.
     */
    public function setTraceId($traceId)
    {
        $this->traceId = $traceId;
    }

    /**
     * Fetch the current spanId.
     *
     * @return string
     */
    public function spanId()
    {
        return $this->spanId;
    }

    /**
     * Set the current spanId.
     *
     * @param string $spanId The spanId to set.
     */
    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    /**
     * Whether or not the request is being traced.
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->enabled;
    }

    /**
     * Set whether or not the request is being traced.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Whether or not this context was detected from a request header.
     *
     * @return bool
     */
    public function fromHeader()
    {
        return $this->fromHeader;
    }

    /**
     * Returns a string form of the TraceContext. This is the format of the Trace Context Header
     * and should be forwarded to downstream requests as the X-Cloud-Trace-Context header.
     *
     * @return string
     */
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
