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
use Google\Cloud\Exception\ServiceException;
use GuzzleHttp\Psr7\Request;

/**
 * Uploader that is a special case of the ResumableUploader where we can write
 * the file contents in a streaming manner.
 */
class StreamableUploader extends AbstractUploader
{
    use ResumableUploadApiTrait;

    /**
     * Triggers the upload process.
     *
     * @param bool $remainder If true, send the all the remaining data and close
     *        the file. Otherwise, only write data if we have enough to send a
     *        chucked set.
     * @return array
     * @throws GoogleException
     */
    public function upload($writeSize = null)
    {
        // find or create the resumeUri
        $resumeUri = $this->getResumeUri();

        $rangeStart = $this->rangeStart;
        if ($writeSize) {
            $rangeEnd = $rangeStart + $writeSize - 1;
            $contentLength = $writeSize;
            $data = $this->data->read($writeSize);
        } else {
            $rangeEnd = '*';
            $data = $this->data->getContents();
            $contentLength = strlen($data);
        }

        $headers = [
            'Content-Length' => $contentLength,
            'Content-Type' => $this->contentType,
            'Content-Range' => "bytes $rangeStart-$rangeEnd/*",
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
                "Upload failed. Please use this URI to resume your upload: $resumeUri",
                $ex->getCode(),
                $ex
            );
        }

        // reset the buffer with the remaining contents
        $this->rangeStart += $contentLength;

        return json_decode($response->getBody(), true);
    }

    private function fullDataSize()
    {
        return 0;
    }

}
