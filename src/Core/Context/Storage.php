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
 * This class maintains storage for Context. In the future, we can implement a thread safe implementation for use
 * with pthreads.
 */
class Storage
{
    /**
     * @var Context
     */
    private $current;

    public function __construct(Context $initialContext = null)
    {
        $this->current = $initialContext ?: new Context();
    }

    public function attach(Context $context)
    {
        $current = $this->current;
        $this->current = $context;
        return $current;
    }

    public function detach(Context $toDetach, Context $toAttach)
    {
        if ($toDetach != $this->current) {
            trigger_error('Context detach mismatch', E_USER_WARNING);
        }
        $this->current = $toAttach;
    }

    public function current()
    {
        return $this->current;
    }
}
