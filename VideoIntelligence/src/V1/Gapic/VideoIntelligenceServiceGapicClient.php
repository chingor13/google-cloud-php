<?php
/*
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * GENERATED CODE WARNING
 * This file was generated from the file
 * https://github.com/google/googleapis/blob/master/google/cloud/videointelligence/v1/video_intelligence.proto
 * and updates to that file get reflected here through a refresh process.
 *
 * @experimental
 */

namespace Google\Cloud\VideoIntelligence\V1\Gapic;

use Google\ApiCore\ApiException;
use Google\ApiCore\CredentialsWrapper;
use Google\ApiCore\FetchAuthTokenInterface;
use Google\ApiCore\GapicClientTrait;
use Google\ApiCore\LongRunning\OperationsClient;
use Google\ApiCore\OperationResponse;
use Google\ApiCore\RetrySettings;
use Google\ApiCore\Transport\TransportInterface;
use Google\ApiCore\ValidationException;
use Google\Cloud\VideoIntelligence\V1\AnnotateVideoProgress;
use Google\Cloud\VideoIntelligence\V1\AnnotateVideoRequest;
use Google\Cloud\VideoIntelligence\V1\AnnotateVideoResponse;
use Google\Cloud\VideoIntelligence\V1\Feature;
use Google\Cloud\VideoIntelligence\V1\VideoContext;
use Google\LongRunning\Operation;

/**
 * Service Description: Service that implements Google Cloud Video Intelligence API.
 *
 * This class provides the ability to make remote calls to the backing service through method
 * calls that map to API methods. Sample code to get started:
 *
 * ```
 * $videoIntelligenceServiceClient = new VideoIntelligenceServiceClient();
 * try {
 *     $inputUri = 'gs://demomaker/cat.mp4';
 *     $featuresElement = Feature::LABEL_DETECTION;
 *     $features = [$featuresElement];
 *     $operationResponse = $videoIntelligenceServiceClient->annotateVideo(['inputUri' => $inputUri, 'features' => $features]);
 *     $operationResponse->pollUntilComplete();
 *     if ($operationResponse->operationSucceeded()) {
 *       $result = $operationResponse->getResult();
 *       // doSomethingWith($result)
 *     } else {
 *       $error = $operationResponse->getError();
 *       // handleError($error)
 *     }
 *
 *     // OR start the operation, keep the operation name, and resume later
 *     $operationResponse = $videoIntelligenceServiceClient->annotateVideo(['inputUri' => $inputUri, 'features' => $features]);
 *     $operationName = $operationResponse->getName();
 *     // ... do other work
 *     $newOperationResponse = $videoIntelligenceServiceClient->resumeOperation($operationName, 'annotateVideo');
 *     while (!$newOperationResponse->isDone()) {
 *         // ... do other work
 *         $newOperationResponse->reload();
 *     }
 *     if ($newOperationResponse->operationSucceeded()) {
 *       $result = $newOperationResponse->getResult();
 *       // doSomethingWith($result)
 *     } else {
 *       $error = $newOperationResponse->getError();
 *       // handleError($error)
 *     }
 * } finally {
 *     $videoIntelligenceServiceClient->close();
 * }
 * ```
 *
 * @experimental
 */
class VideoIntelligenceServiceGapicClient
{
    use GapicClientTrait;

    /**
     * The name of the service.
     */
    const SERVICE_NAME = 'google.cloud.videointelligence.v1.VideoIntelligenceService';

    /**
     * The default address of the service.
     */
    const SERVICE_ADDRESS = 'videointelligence.googleapis.com';

    /**
     * The default port of the service.
     */
    const DEFAULT_SERVICE_PORT = 443;

    /**
     * The name of the code generator, to be included in the agent header.
     */
    const CODEGEN_NAME = 'gapic';

    /**
     * The default scopes required by the service.
     */
    public static $serviceScopes = [
        'https://www.googleapis.com/auth/cloud-platform',
    ];

    private $operationsClient;

    private static function getClientDefaults()
    {
        return [
            'serviceName' => self::SERVICE_NAME,
            'serviceAddress' => self::SERVICE_ADDRESS.':'.self::DEFAULT_SERVICE_PORT,
            'clientConfig' => __DIR__.'/../resources/video_intelligence_service_client_config.json',
            'descriptorsConfigPath' => __DIR__.'/../resources/video_intelligence_service_descriptor_config.php',
            'credentialsConfig' => [
                'scopes' => self::$serviceScopes,
            ],
            'transportConfig' => [
                'rest' => [
                    'restClientConfigPath' => __DIR__.'/../resources/video_intelligence_service_rest_client_config.php',
                ],
            ],
        ];
    }

    /**
     * Return an OperationsClient object with the same endpoint as $this.
     *
     * @return OperationsClient
     * @experimental
     */
    public function getOperationsClient()
    {
        return $this->operationsClient;
    }

    /**
     * Resume an existing long running operation that was previously started
     * by a long running API method. If $methodName is not provided, or does
     * not match a long running API method, then the operation can still be
     * resumed, but the OperationResponse object will not deserialize the
     * final response.
     *
     * @param string $operationName The name of the long running operation
     * @param string $methodName    The name of the method used to start the operation
     *
     * @return OperationResponse
     * @experimental
     */
    public function resumeOperation($operationName, $methodName = null)
    {
        $options = isset($this->descriptors[$methodName]['longRunning'])
            ? $this->descriptors[$methodName]['longRunning']
            : [];
        $operation = new OperationResponse($operationName, $this->getOperationsClient(), $options);
        $operation->reload();

        return $operation;
    }

    /**
     * Constructor.
     *
     * @param array $options {
     *                       Optional. Options for configuring the service API wrapper.
     *
     *     @type string $serviceAddress
     *           The address of the API remote host. May optionally include the port, formatted
     *           as "<uri>:<port>". Default 'videointelligence.googleapis.com:443'.
     *     @type string|array|FetchAuthTokenInterface|CredentialsWrapper $credentials
     *           The credentials to be used by the client to authorize API calls. This option
     *           accepts either a path to a credentials file, or a decoded credentials file as a
     *           PHP array.
     *           *Advanced usage*: In addition, this option can also accept a pre-constructed
     *           {@see \Google\Auth\FetchAuthTokenInterface} object or
     *           {@see \Google\ApiCore\CredentialsWrapper} object. Note that when one of these
     *           objects are provided, any settings in $credentialsConfig will be ignored.
     *     @type array $credentialsConfig
     *           Options used to configure credentials, including auth token caching, for the client.
     *           For a full list of supporting configuration options, see
     *           {@see \Google\ApiCore\CredentialsWrapper::build()}.
     *     @type bool $disableRetries
     *           Determines whether or not retries defined by the client configuration should be
     *           disabled. Defaults to `false`.
     *     @type string|array $clientConfig
     *           Client method configuration, including retry settings. This option can be either a
     *           path to a JSON file, or a PHP array containing the decoded JSON data.
     *           By default this settings points to the default client config file, which is provided
     *           in the resources folder.
     *     @type string|TransportInterface $transport
     *           The transport used for executing network requests. May be either the string `rest`
     *           or `grpc`. Defaults to `grpc` if gRPC support is detected on the system.
     *           *Advanced usage*: Additionally, it is possible to pass in an already instantiated
     *           {@see \Google\ApiCore\Transport\TransportInterface} object. Note that when this
     *           object is provided, any settings in $transportConfig, and any $serviceAddress
     *           setting, will be ignored.
     *     @type array $transportConfig
     *           Configuration options that will be used to construct the transport. Options for
     *           each supported transport type should be passed in a key for that transport. For
     *           example:
     *           $transportConfig = [
     *               'grpc' => [...],
     *               'rest' => [...]
     *           ];
     *           See the {@see \Google\ApiCore\Transport\GrpcTransport::build()} and
     *           {@see \Google\ApiCore\Transport\RestTransport::build()} methods for the
     *           supported options.
     * }
     *
     * @throws ValidationException
     * @experimental
     */
    public function __construct(array $options = [])
    {
        $clientOptions = $this->buildClientOptions($options);
        $this->setClientOptions($clientOptions);
        $this->operationsClient = $this->createOperationsClient($clientOptions);
    }

    /**
     * Performs asynchronous video annotation. Progress and results can be
     * retrieved through the `google.longrunning.Operations` interface.
     * `Operation.metadata` contains `AnnotateVideoProgress` (progress).
     * `Operation.response` contains `AnnotateVideoResponse` (results).
     *
     * Sample code:
     * ```
     * $videoIntelligenceServiceClient = new VideoIntelligenceServiceClient();
     * try {
     *     $inputUri = 'gs://demomaker/cat.mp4';
     *     $featuresElement = Feature::LABEL_DETECTION;
     *     $features = [$featuresElement];
     *     $operationResponse = $videoIntelligenceServiceClient->annotateVideo(['inputUri' => $inputUri, 'features' => $features]);
     *     $operationResponse->pollUntilComplete();
     *     if ($operationResponse->operationSucceeded()) {
     *       $result = $operationResponse->getResult();
     *       // doSomethingWith($result)
     *     } else {
     *       $error = $operationResponse->getError();
     *       // handleError($error)
     *     }
     *
     *     // OR start the operation, keep the operation name, and resume later
     *     $operationResponse = $videoIntelligenceServiceClient->annotateVideo(['inputUri' => $inputUri, 'features' => $features]);
     *     $operationName = $operationResponse->getName();
     *     // ... do other work
     *     $newOperationResponse = $videoIntelligenceServiceClient->resumeOperation($operationName, 'annotateVideo');
     *     while (!$newOperationResponse->isDone()) {
     *         // ... do other work
     *         $newOperationResponse->reload();
     *     }
     *     if ($newOperationResponse->operationSucceeded()) {
     *       $result = $newOperationResponse->getResult();
     *       // doSomethingWith($result)
     *     } else {
     *       $error = $newOperationResponse->getError();
     *       // handleError($error)
     *     }
     * } finally {
     *     $videoIntelligenceServiceClient->close();
     * }
     * ```
     *
     * @param array $optionalArgs {
     *                            Optional.
     *
     *     @type string $inputUri
     *          Input video location. Currently, only
     *          [Google Cloud Storage](https://cloud.google.com/storage/) URIs are
     *          supported, which must be specified in the following format:
     *          `gs://bucket-id/object-id` (other URI formats return
     *          [google.rpc.Code.INVALID_ARGUMENT][google.rpc.Code.INVALID_ARGUMENT]). For more information, see
     *          [Request URIs](https://cloud.google.com/storage/docs/reference-uris).
     *          A video URI may include wildcards in `object-id`, and thus identify
     *          multiple videos. Supported wildcards: '*' to match 0 or more characters;
     *          '?' to match 1 character. If unset, the input video should be embedded
     *          in the request as `input_content`. If set, `input_content` should be unset.
     *     @type string $inputContent
     *          The video data bytes.
     *          If unset, the input video(s) should be specified via `input_uri`.
     *          If set, `input_uri` should be unset.
     *     @type int[] $features
     *          Requested video annotation features.
     *          For allowed values, use constants defined on {@see \Google\Cloud\VideoIntelligence\V1\Feature}
     *     @type VideoContext $videoContext
     *          Additional video context and/or feature-specific parameters.
     *     @type string $outputUri
     *          Optional location where the output (in JSON format) should be stored.
     *          Currently, only [Google Cloud Storage](https://cloud.google.com/storage/)
     *          URIs are supported, which must be specified in the following format:
     *          `gs://bucket-id/object-id` (other URI formats return
     *          [google.rpc.Code.INVALID_ARGUMENT][google.rpc.Code.INVALID_ARGUMENT]). For more information, see
     *          [Request URIs](https://cloud.google.com/storage/docs/reference-uris).
     *     @type string $locationId
     *          Optional cloud region where annotation should take place. Supported cloud
     *          regions: `us-east1`, `us-west1`, `europe-west1`, `asia-east1`. If no region
     *          is specified, a region will be determined based on video file location.
     *     @type RetrySettings|array $retrySettings
     *          Retry settings to use for this call. Can be a
     *          {@see Google\ApiCore\RetrySettings} object, or an associative array
     *          of retry settings parameters. See the documentation on
     *          {@see Google\ApiCore\RetrySettings} for example usage.
     * }
     *
     * @return \Google\ApiCore\OperationResponse
     *
     * @throws ApiException if the remote call fails
     * @experimental
     */
    public function annotateVideo(array $optionalArgs = [])
    {
        $request = new AnnotateVideoRequest();
        if (isset($optionalArgs['inputUri'])) {
            $request->setInputUri($optionalArgs['inputUri']);
        }
        if (isset($optionalArgs['inputContent'])) {
            $request->setInputContent($optionalArgs['inputContent']);
        }
        if (isset($optionalArgs['features'])) {
            $request->setFeatures($optionalArgs['features']);
        }
        if (isset($optionalArgs['videoContext'])) {
            $request->setVideoContext($optionalArgs['videoContext']);
        }
        if (isset($optionalArgs['outputUri'])) {
            $request->setOutputUri($optionalArgs['outputUri']);
        }
        if (isset($optionalArgs['locationId'])) {
            $request->setLocationId($optionalArgs['locationId']);
        }

        return $this->startOperationsCall(
            'AnnotateVideo',
            $optionalArgs,
            $request,
            $this->getOperationsClient()
        )->wait();
    }
}
