<?php

namespace Google\Cloud\Core\Context;

class Context
{
    const HTTP_HEADER = 'HTTP_X_CLOUD_TRACE_CONTEXT';
    const CONTEXT_HEADER_FORMAT = '/([0-9a-f]{32})(?:\/(\d+))?(?:;o=(\d+))?/';

    private static $stack;

    private $values;

    public static function current()
    {
        self::$stack = self::$stack ?: [self::fromServer()];
        return self::$stack[0];
    }

    public static function attach($context)
    {
        $current = self::current();
        array_unshift(self::$stack, $context);
        return $current;
    }

    public static function detach()
    {
        return array_shift(self::$stack) ?: new static();
    }

    public static function fromServer($server = null)
    {
        $server = $server ?: $_SERVER;
        $values = [];

        if (isset($server['GCLOUD_PROJECT'])) {
            $values['projectId'] = $server['GCLOUD_PROJECT'];
        }

        if (isset($server['GAE_SERVICE'])) {
            $values['serviceId'] = $server['GAE_SERVICE'];
        }

        if (isset($server['GAE_VERSION'])) {
            $values['versionId'] = $server['GAE_VERSION'];
        }

        if (array_key_exists(self::HTTP_HEADER, $server) &&
            preg_match(self::CONTEXT_HEADER_FORMAT, $server[self::HTTP_HEADER], $matches)) {
            $values['traceId'] = $matches[1];

            if (isset($matches[2])) {
                $values['spanId'] = $matches[2];
            }

            if (isset($matches[3])) {
                $values['traceEnabled'] = $matches[3] == '1';
            }

            $values['traceSampledFromHeader'] = true;
        }
        return new static($values);
    }

    public function __construct($initialValues = [])
    {
        $this->values = $initialValues;
    }

    public function withValues(array $values = [])
    {
        return new static($values + $this->values);
    }

    public function withValue($key, $value)
    {
        return $this->withValues([$key => $value]);
    }

    public function value($key)
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : null;
    }

    public function wrap($callable)
    {
        $context = $this;
        return function () use ($context, $callable) {
            $args = func_get_args();
            Context::attach($context);
            try {
                $ret = call_user_func_array($callable, $args);
            } finally {
                Context::detach();
            }
            return $ret;
        };
    }
}
