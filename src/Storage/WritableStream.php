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

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * This stream interface is writable and will try to upload chunks to
 * the server whenver we receive enough bytes to transmit a chunk. The remaining
 * bytes are sent when the stream is closed.
 */
class WritableStream implements StreamInterface
{
    use StreamDecoratorTrait;

    const DEFAULT_WRITE_CHUNK_SIZE = 262144;

    private $chunkSize;

    public function __construct($bucket, $filename, $options)
    {
        $this->chunkSize = isset($options['chunkSize']) ? $options['chunkSize'] : self::DEFAULT_WRITE_CHUNK_SIZE;
        $this->stream = new BufferStream($this->chunkSize);
        $this->uploader = $bucket->getStreamableUploader($this, $options + [
            'name'      => $filename,
            'chunkSize' => $this->chunkSize
        ]);
        $this->uploader->getResumeUri();
    }

    public function write($string)
    {
        $length = $this->stream->write($string);

        if ($length === false) {
            $this->upload(false);
        }

        return $length;
    }

    public function close()
    {
        // on close, write the remaining data
        if ($this->uploader) {
            try {
                $this->upload(true);
            } catch (\Exception $e) {
                $this->uploader = null;
                throw $e;
            }
            $this->uploader = null;
        }
    }

    public function isWritable()
    {
        return true;
    }

    public function tell()
    {
        return 0;
    }

    public function getChunkedStream()
    {
        return new LimitStream(
            $this,
            $this->getChunkedWriteSize(),
            0
        );
    }

    private function resetBuffer($data = null)
    {
        $this->stream = new BufferStream($this->chunkSize);
        if ($data) {
            $this->stream->write($data);
        }
    }

    private function upload($remainder = true)
    {
        $writeSize = $this->getChunkedWriteSize($remainder);
        $this->uploader->upload($writeSize);

        if (!$remainder) {
            $leftOver = $this->stream->getContents();
            $this->resetBuffer($leftOver);
        }
    }

    private function getChunkedWriteSize($remainder = false)
    {
        $bufferSize = $this->stream->getSize();
        if ($remainder) {
            return null;
        } else {
            return (int) floor($bufferSize / $this->chunkSize) * $this->chunkSize;
        }
    }
}
