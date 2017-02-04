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

namespace Google\Cloud\Storage;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * This stream class wraps a lazily downloaded stream. We need the size of the
 * content in order to do copy operations. This should be available in the
 * HTTP Content-Length header.
 */
class LazyReadString implements StreamInterface
{
    use StreamDecoratorTrait;

    public function __construct($bucket, $filename, $options)
    {
        $options['httpOptions']['stream'] = true;
        $this->stream = $bucket->object($filename)->downloadAsStream($options));
    }

    public function getSize()
    {
        return $this->stream->getSize() ?: $this->getSizeFromHeaders();
    }

    private function getSizeFromHeaders()
    {
        foreach ($this->getMetadata('wrapper_data')) as $value) {
            if (substr($value, 0, 15) == "Content-Length:") {
                return (int) substr($value, 16);
            }
        }
        return 0;
    }
}
