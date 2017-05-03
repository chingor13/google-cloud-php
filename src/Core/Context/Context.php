<?php
/**
 * Copyright 2017 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Core\Context;

/**
 * This class maintains generic context throughout the life of a request.
 * Each Context instance should be immutable and inherit values from its parent.
 */
class Context
{
    /**
     * @var Storage
     */
    private static $storage;

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
        return self::storage()->current();
    }

    /**
     * Returns the context storage mechanism
     *
     * @return Storage
     */
    protected static function storage()
    {
        self::$storage = self::$storage ?: new Storage();
        return self::$storage;
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
     * Set the current active Context
     *
     * @param Context $context The new context
     * @return Context The previously active context
     */
    public function attach()
    {
        return self::storage()->attach($this);
    }

    /**
     * Restore the previously active context. It is expected that
     * if the current context does not match this Context, a
     * warning will be raised. In this case, the previous context will
     * still be attached as the current one.
     *
     * @return Context The previously active context
     */
    public function detach(Context $previous)
    {
        return self::storage()->detach($this, $previous);
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
     * Call a callable whose execution uses this as the current context.
     *
     * @param callable $callable The callable to wrap
     * @param array $args [optional] Arguments for the callable. **Defaults to** an empty array.
     * @return mixed Whatever the callable returns
     */
    public function call(callable $callable, $args = [])
    {
        $previous = $this->attach();
        try {
            return call_user_func_array($callable, $args);
        } finally {
            $this->detach($previous);
        }
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
            $previous = $context->attach();
            try {
                return call_user_func_array($callable, $args);
            } finally {
                $context->detach($previous);
            }
        };
    }
}
