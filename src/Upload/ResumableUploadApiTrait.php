<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may ob`tain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Upload;

use Google\Cloud\Exception\GoogleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

trait ResumableUploadApiTrait
{
    /**
     * @var int
     */
    private $rangeStart = 0;

    /**
     * @var string
     */
    private $resumeUri;

    /**
     * Gets the resume URI.
     *
     * @return string
     */
    public function getResumeUri()
    {
        if (!$this->resumeUri) {
            return $this->createResumeUri();
        }

        return $this->resumeUri;
    }

    /**
     * Resumes a download using the provided URI.
     *
     * @param string $resumeUri
     * @return array
     * @throws GoogleException
     */
    public function resume($resumeUri)
    {
        if (!$this->data->isSeekable()) {
            throw new GoogleException('Cannot resume upload on a stream which cannot be seeked.');
        }

        $this->resumeUri = $resumeUri;
        $response = $this->getStatusResponse();

        if ($response->getBody()->getSize() > 0) {
            return json_decode($response->getBody(), true);
        }

        $this->rangeStart = $this->getRangeStart($response->getHeaderLine('Range'));

        return $this->upload();
    }

    /**
     * Creates the resume URI.
     *
     * @return string
     */
    private function createResumeUri()
    {
        $headers = [
            'X-Upload-Content-Type' => $this->contentType,
            'X-Upload-Content-Length' => $this->fullDataSize(),
            'Content-Type' => 'application/json'
        ];

        $request = new Request(
            'POST',
            $this->uri,
            $headers,
            json_encode($this->metadata)
        );

        $response = $this->requestWrapper->send($request, $this->requestOptions);
        $this->resumeUri = $response->getHeaderLine('Location');

        return $this->resumeUri;
    }

    private function fullDataSize()
    {
        return $this->data->getSize();
    }

    /**
     * Gets the status of the upload.
     *
     * @return ResponseInterface
     */
    private function getStatusResponse()
    {
        $request = new Request(
            'PUT',
            $this->resumeUri,
            ['Content-Range' => 'bytes */*']
        );

        return $this->requestWrapper->send($request, $this->requestOptions);
    }

    /**
     * Gets the starting range for the upload.
     *
     * @param string $rangeHeader
     * @return int
     */
    private function getRangeStart($rangeHeader)
    {
        if (!$rangeHeader) {
            return null;
        }

        return (int) explode('-', $rangeHeader)[1] + 1;
    }
}
