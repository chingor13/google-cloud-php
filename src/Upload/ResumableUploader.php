<?php
/**
 * Copyright 2016 Google Inc. All Rights Reserved.
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

namespace Google\Cloud\Upload;

use Google\Cloud\Exception\GoogleException;
use Google\Cloud\Exception\ServiceException;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Request;

/**
 * Resumable upload implementation.
 */
class ResumableUploader extends AbstractUploader
{
    use ResumableUploadApiTrait;

    /**
     * Triggers the upload process.
     *
     * @return array
     * @throws GoogleException
     */
    public function upload()
    {
        $rangeStart = $this->rangeStart;
        $response = null;
        $resumeUri = $this->getResumeUri();
        $size = $this->data->getSize() ?: '*';

        do {
            $data = new LimitStream(
                $this->data,
                $this->chunkSize ?: - 1,
                $rangeStart
            );
            $rangeEnd = $rangeStart + ($data->getSize() - 1);
            $headers = [
                'Content-Length' => $data->getSize(),
                'Content-Type' => $this->contentType,
                'Content-Range' => "bytes $rangeStart-$rangeEnd/$size",
            ];

            $request = new Request(
                'PUT',
                $resumeUri,
                $headers,
                $data
            );

            try {
                $response = $this->requestWrapper->send($request, $this->requestOptions);
            } catch (ServiceException $ex) {
                throw new GoogleException(
                    "Upload failed. Please use this URI to resume your upload: $this->resumeUri",
                    $ex->getCode()
                );
            }

            $rangeStart = $this->getRangeStart($response->getHeaderLine('Range'));
        } while ($response->getStatusCode() === 308);

        return json_decode($response->getBody(), true);
    }

}
