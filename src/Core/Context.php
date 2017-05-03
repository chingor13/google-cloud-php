<?php

namespace Google\Cloud\Core;

class Context
{
    /**
     * @var Context[]
     */
    private static $stack;

    /**
     * @var array Context values
     */
    private $values;

    /**
     * Return the current active Context
     *
     * @return Context
     */
    public static function current()
    {
        self::$stack = self::$stack ?: [new static()];
        return self::$stack[0];
    }

    /**
     * Set the current active Context
     *
     * @param Context $context The new context
     * @return Context The previously active context
     */
    public static function attach($context)
    {
        $current = self::current();
        array_unshift(self::$stack, $context);
        return $current;
    }

    /**
     * Restore the previously active context
     *
     * @return Context The previously active context
     */
    public static function detach()
    {
        if (!isset(self::$stack) || empty(self::$stack)) {
            return null;
        }
        return array_shift(self::$stack) ?: new static();
    }

    /**
     * Create a new Context
     *
     * @param array $initialValues Initial context values
     */
    public function __construct(array $initialValues = [])
    {
        $this->values = $initialValues;
    }

    /**
     * Create a new Context inheriting values from itself
     *
     * @param array $values New context values to merge
     * @return Context
     */
    public function withValues(array $values = [])
    {
        return new static($values + $this->values);
    }

    /**
     * Create a new Context inheriting values from itself
     *
     * @param string $key New context key
     * @param mixed $value New context value
     * @return Context
     */
    public function withValue($key, $value)
    {
        return $this->withValues([$key => $value]);
    }

    /**
     * Fetch the value associated with the key.
     *
     * @param string $key The context key
     * @return mixed The associated context value
     */
    public function value($key)
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : null;
    }

    /**
     * Return a callable whose execution uses this as the current context.
     *
     * @param callable $callable The callable to wrap
     * @return \Closure
     */
    public function wrap(callable $callable)
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
