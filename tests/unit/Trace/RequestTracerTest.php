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

namespace Google\Cloud\Tests\Unit\Trace;

use Google\Cloud\Trace\TraceSpan;
use Google\Cloud\Trace\Tracer\ContextTracer;
use Google\Cloud\Trace\Tracer\NullTracer;
use Google\Cloud\Trace\Reporter\ReporterInterface;
use Google\Cloud\Trace\RequestTracer;

/**
 * @group trace
 */
class RequestTracerTest extends \PHPUnit_Framework_TestCase
{
    private $reporter;

    public function setUp()
    {
        $this->reporter = $this->prophesize(ReporterInterface::class);
    }

    public function testForceDisabled()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['enabled' => false]);
        $tracer = $rt->tracer();

        $this->assertFalse($rt->enabled());
        $this->assertInstanceOf(NullTracer::class, $tracer);
    }

    public function testForceEnabled()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['enabled' => true]);
        $tracer = $rt->tracer();

        $this->assertTrue($rt->enabled());
        $this->assertInstanceOf(ContextTracer::class, $tracer);
    }

    public function testForceEnabledContextHeader()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['headers' => [
            'HTTP_X_CLOUD_TRACE_CONTEXT' => '12345678901234567890123456789012;o=1'
        ]]);
        $tracer = $rt->tracer();

        $this->assertTrue($rt->enabled());
        $this->assertInstanceOf(ContextTracer::class, $tracer);
    }

    public function testForceDisabledContextHeader()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['headers' => [
            'HTTP_X_CLOUD_TRACE_CONTEXT' => '12345678901234567890123456789012;o=0'
        ]]);
        $tracer = $rt->tracer();

        $this->assertFalse($rt->enabled());
        $this->assertInstanceOf(NullTracer::class, $tracer);
    }

    public function testCanTrackContext()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['enabled' => true]);
        RequestTracer::instrument(['name' => 'inner'], function () {});
        $rt->onExit();
        $spans = $rt->tracer()->spans();
        $this->assertCount(2, $spans);
        foreach ($spans as $span) {
            $this->assertInstanceOf(TraceSpan::class, $span);
            $this->assertArrayHasKey('endTime', $span->info());
        }
        $this->assertEquals('main', $spans[0]->name());
        $this->assertEquals('inner', $spans[1]->name());
        $this->assertEquals($spans[0]->spanId(), $spans[1]->info()['parentSpanId']);
    }

    public function testCanParseLabels()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['enabled' => true, 'headers' => [
            'REQUEST_URI' => '/some/uri',
            'REQUEST_METHOD' => 'POST',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_USER_AGENT' => 'test agent 0.1',
            'HTTP_HOST' => 'example.com:8080',
            'GAE_SERVICE' => 'test app',
            'GAE_VERSION' => 'some version'
        ]]);
        $span = $rt->tracer()->spans()[0];
        $labels = $span->info()['labels'];
        $expectedLabels = [
            '/http/url' => '/some/uri',
            '/http/method' => 'POST',
            '/http/client_protocol' => 'HTTP/1.1',
            '/http/user_agent' => 'test agent 0.1',
            '/http/host' => 'example.com:8080',
            'g.co/gae/app/module' => 'test app',
            'g.co/gae/app/version' => 'some version'
        ];

        foreach ($expectedLabels as $key => $value) {
            $this->assertArrayHasKey($key, $labels);
            $this->assertEquals($value, $labels[$key]);
        }
        $this->assertArrayHasKey('/pid', $labels);
        $this->assertArrayHasKey('/agent', $labels);
        $this->assertEquals('google-cloud-php', $labels['/agent']);
    }

    public function testCanParseParentContext()
    {
        $rt = RequestTracer::start($this->reporter->reveal(), ['headers' => [
            'HTTP_X_CLOUD_TRACE_CONTEXT' => '12345678901234567890123456789012/0000;o=1'
        ]]);
        $span = $rt->tracer()->spans()[0];
        $this->assertEquals('0000', $span->info()['parentSpanId']);
    }
}
