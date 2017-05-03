<?php

namespace Google\Cloud\Core\Context;

class LoggingContext
{
    private $traceId;
    private $projectId;
    private $serviceId;
    private $versionId;

    public static function fromContext(Context $context = null)
    {
        $context = $context ?: Context::current();
        return new static(
            $context->value('traceId'),
            $context->value('projectId'),
            $context->value('serviceId'),
            $context->value('versionId')
        );
    }

    public function __construct($traceId, $projectId, $serviceId, $versionId)
    {
        $this->traceId = $traceId;
        $this->projectId = $projectId;
        $this->serviceId = $serviceId;
        $this->versionId = $versionId;
    }

    public function traceId()
    {
        return $this->traceId;
    }

    public function projectId()
    {
        return $this->projectId;
    }

    public function serviceId()
    {
        return $this->serviceId;
    }

    public function versionId()
    {
        return $this->$versionId;
    }
}
