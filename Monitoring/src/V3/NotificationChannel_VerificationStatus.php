<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/monitoring/v3/notification.proto

namespace Google\Cloud\Monitoring\V3;

/**
 * Indicates whether the channel has been verified or not. It is illegal
 * to specify this field in a
 * [`CreateNotificationChannel`][google.monitoring.v3.NotificationChannelService.CreateNotificationChannel]
 * or an
 * [`UpdateNotificationChannel`][google.monitoring.v3.NotificationChannelService.UpdateNotificationChannel]
 * operation.
 *
 * Protobuf enum <code>Google\Monitoring\V3\NotificationChannel\VerificationStatus</code>
 */
class NotificationChannel_VerificationStatus
{
    /**
     * Sentinel value used to indicate that the state is unknown, omitted, or
     * is not applicable (as in the case of channels that neither support
     * nor require verification in order to function).
     *
     * Generated from protobuf enum <code>VERIFICATION_STATUS_UNSPECIFIED = 0;</code>
     */
    const VERIFICATION_STATUS_UNSPECIFIED = 0;
    /**
     * The channel has yet to be verified and requires verification to function.
     * Note that this state also applies to the case where the verification
     * process has been initiated by sending a verification code but where
     * the verification code has not been submitted to complete the process.
     *
     * Generated from protobuf enum <code>UNVERIFIED = 1;</code>
     */
    const UNVERIFIED = 1;
    /**
     * It has been proven that notifications can be received on this
     * notification channel and that someone on the project has access
     * to messages that are delivered to that channel.
     *
     * Generated from protobuf enum <code>VERIFIED = 2;</code>
     */
    const VERIFIED = 2;
}

