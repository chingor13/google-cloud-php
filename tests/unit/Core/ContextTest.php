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

namespace Google\Cloud\Tests\Unit\Core;

use Google\Cloud\Core\Context;

/**
 * @group core
 */
class ContextTest extends \PHPUnit_Framework_TestCase
{
    public function testAddingValuesCreatesNewObject()
    {
        $context = new Context();
        $context2 = $context->withValues(['foo' => 'bar']);

        $this->assertNotEquals(spl_object_hash($context), spl_object_hash($context2));
        $this->assertNull($context->value('foo'));
        $this->assertEquals('bar', $context2->value('foo'));
    }

    public function testAddingValueCreatesNewObject()
    {
        $context = new Context();
        $context2 = $context->withValue('foo', 'bar');

        $this->assertNotEquals(spl_object_hash($context), spl_object_hash($context2));
        $this->assertNull($context->value('foo'));
        $this->assertEquals('bar', $context2->value('foo'));
    }

    public function plus($x, $y)
    {
        return $x + $y + Context::current()->value('z');
    }

    public function testWrap()
    {
        $context = new Context();
        $context = $context->withValue('z', 3);
        $func = $context->wrap([$this, 'plus']);
        $ret = call_user_func_array($func, [1, 2]);
        $this->assertEquals(6, $ret);
    }
}
