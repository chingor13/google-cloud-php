<?php

namespace Google\Cloud\Core\Context;

class ErrorReportingContext
{
    private $serviceId;
    private $versionId;

    public static function fromContext(Context $context = null)
    {
        $context = $context ?: Context::current();
        return new static(
            $context->value('serviceId'),
            $context->value('versionId')
        );
    }

    public function __construct($serviceId, $versionId)
    {
        $this->serviceId = $serviceId;
        $this->versionId = $versionId;
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
