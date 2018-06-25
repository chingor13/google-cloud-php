<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/pubsub/v1/pubsub.proto

namespace Google\Cloud\PubSub\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Request for the `ListSnapshots` method.<br><br>
 * <b>ALPHA:</b> This feature is part of an alpha release. This API might be
 * changed in backward-incompatible ways and is not recommended for production
 * use. It is not subject to any SLA or deprecation policy.
 *
 * Generated from protobuf message <code>google.pubsub.v1.ListSnapshotsRequest</code>
 */
class ListSnapshotsRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * The name of the cloud project that snapshots belong to.
     * Format is `projects/{project}`.
     *
     * Generated from protobuf field <code>string project = 1;</code>
     */
    private $project = '';
    /**
     * Maximum number of snapshots to return.
     *
     * Generated from protobuf field <code>int32 page_size = 2;</code>
     */
    private $page_size = 0;
    /**
     * The value returned by the last `ListSnapshotsResponse`; indicates that this
     * is a continuation of a prior `ListSnapshots` call, and that the system
     * should return the next page of data.
     *
     * Generated from protobuf field <code>string page_token = 3;</code>
     */
    private $page_token = '';

    public function __construct() {
        \GPBMetadata\Google\Pubsub\V1\Pubsub::initOnce();
        parent::__construct();
    }

    /**
     * The name of the cloud project that snapshots belong to.
     * Format is `projects/{project}`.
     *
     * Generated from protobuf field <code>string project = 1;</code>
     * @return string
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * The name of the cloud project that snapshots belong to.
     * Format is `projects/{project}`.
     *
     * Generated from protobuf field <code>string project = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setProject($var)
    {
        GPBUtil::checkString($var, True);
        $this->project = $var;

        return $this;
    }

    /**
     * Maximum number of snapshots to return.
     *
     * Generated from protobuf field <code>int32 page_size = 2;</code>
     * @return int
     */
    public function getPageSize()
    {
        return $this->page_size;
    }

    /**
     * Maximum number of snapshots to return.
     *
     * Generated from protobuf field <code>int32 page_size = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setPageSize($var)
    {
        GPBUtil::checkInt32($var);
        $this->page_size = $var;

        return $this;
    }

    /**
     * The value returned by the last `ListSnapshotsResponse`; indicates that this
     * is a continuation of a prior `ListSnapshots` call, and that the system
     * should return the next page of data.
     *
     * Generated from protobuf field <code>string page_token = 3;</code>
     * @return string
     */
    public function getPageToken()
    {
        return $this->page_token;
    }

    /**
     * The value returned by the last `ListSnapshotsResponse`; indicates that this
     * is a continuation of a prior `ListSnapshots` call, and that the system
     * should return the next page of data.
     *
     * Generated from protobuf field <code>string page_token = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setPageToken($var)
    {
        GPBUtil::checkString($var, True);
        $this->page_token = $var;

        return $this;
    }

}

