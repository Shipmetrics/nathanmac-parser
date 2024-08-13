<?php

namespace Nathanmac\Utilities\Parser\Tests;

use \Mockery as m;
use Nathanmac\Utilities\Parser\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /**
     * Tear down after tests
     */
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_mask_payload()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"message": {"title": "Hello World", "body": "Some message content"}, "comments": [{ "title": "hello", "message": "hello world", "tags": ["one", "two"]}, {"title": "world", "message": "hello world", "tags": ["red", "green"]}]}');

        $this->assertEquals(["message" => ["title" => "Hello World"]], $parser->mask(['message' => ['title' => '*']]));
        $this->assertEquals(["comments" => [["title" => "hello", "message" => "hello world", "tags" => ["one", "two"]], ["title" => "world", "message" => "hello world", "tags" => ["red", "green"]]]], $parser->mask(['comments' => '*']));
        $this->assertEquals(['posts' => null], $parser->mask(['posts' => '*']));
    }

    public function test_wildcards_with_simple_structure_json()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"email": {"to": "jane.doe@example.com", "from": "john.doe@example.com", "subject": "Hello World", "message": { "body": "Hello this is a sample message" }}}');

        $this->assertTrue($parser->has('email.to'));
        $this->assertTrue($parser->has('email.message.*'));
        $this->assertTrue($parser->has('email.message.%'));
        $this->assertTrue($parser->has('email.message.:first'));
        $this->assertTrue($parser->has('email.message.:last'));
        $this->assertFalse($parser->has('message.email.*'));
        $this->assertFalse($parser->has('message.email.%'));
        $this->assertFalse($parser->has('message.email.:first'));
        $this->assertFalse($parser->has('message.email.:last'));
        $this->assertEquals("Hello this is a sample message", $parser->get('email.message.%'));
        $this->assertEquals("Hello this is a sample message", $parser->get('email.message.:first'));
        $this->assertEquals("jane.doe@example.com", $parser->get('email.*'));
        $this->assertEquals("jane.doe@example.com", $parser->get('email.:first'));
        $this->assertEquals(['body' => 'Hello this is a sample message'], $parser->get('email.:last'));
        $this->assertEquals("jane.doe@example.com", $parser->get('email.:index[0]'));
        $this->assertEquals("john.doe@example.com", $parser->get('email.:index[1]'));
    }

    public function test_wildcards_with_array_structure_json()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"comments": [{ "title": "hello", "message": "hello world"}, {"title": "world", "message": "world hello"}]}');

        $this->assertTrue($parser->has('comments.*.title'));
        $this->assertTrue($parser->has('comments.%.title'));
        $this->assertTrue($parser->has('comments.:index[1].title'));
        $this->assertTrue($parser->has('comments.:first.title'));
        $this->assertTrue($parser->has('comments.:last.title'));
        $this->assertEquals('hello', $parser->get('comments.:index[0].title'));
        $this->assertEquals('world', $parser->get('comments.:index[1].title'));
        $this->assertEquals('world', $parser->get('comments.:last.title'));
        $this->assertEquals('hello', $parser->get('comments.*.title'));
        $this->assertFalse($parser->has('comments.:index[99]'));
        $this->assertFalse($parser->has('comments.:index[99].title'));
        $this->assertEquals(['title' => 'hello', 'message' => 'hello world'], $parser->get('comments.*'));
        $this->assertEquals(['title' => 'hello', 'message' => 'hello world'], $parser->get('comments.%'));
        $this->assertEquals(['title' => 'hello', 'message' => 'hello world'], $parser->get('comments.:first'));
        $this->assertEquals(['title' => 'world', 'message' => 'world hello'], $parser->get('comments.:last'));
        $this->assertEquals(['title' => 'hello', 'message' => 'hello world'], $parser->get('comments.:index[0]'));
        $this->assertEquals(['title' => 'world', 'message' => 'world hello'], $parser->get('comments.:index[1]'));
    }

    public function test_array_structured_getPayload_json()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->once()
            ->andReturn('{"comments": [{ "title": "hello", "message": "hello world"}, {"title": "world", "message": "hello world"}]}');

        $this->assertEquals(["comments" => [["title" => "hello", "message" => "hello world"], ["title" => "world", "message" => "hello world"]]], $parser->payload());
    }

    public function test_alias_all_check()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->once()
            ->andReturn('{"status":123, "message":"hello world"}');

        $this->assertEquals(['status' => 123, 'message' => 'hello world'], $parser->all());
    }

    public function test_return_value_for_multi_level_key()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"id": 123, "note": {"headers": {"to": "example@example.com", "from": "example@example.com"}, "body": "Hello World"}}');

        $this->assertEquals('123', $parser->get('id'));
        $this->assertEquals('Hello World', $parser->get('note.body'));
        $this->assertEquals('example@example.com', $parser->get('note.headers.to'));
        $this->assertTrue($parser->has('note.headers.to'));

        $this->assertEquals(['id' => 123, 'note' => ['headers' => ['from' => 'example@example.com'], 'body' => 'Hello World']], $parser->except('note.headers.to'));
        $this->assertEquals(['id' => 123, 'note' => ['headers' => ['to' => 'example@example.com', 'from' => 'example@example.com']]], $parser->except('note.body'));

        $this->assertEquals(['note' => ['headers' => ['to' => 'example@example.com', 'from' => 'example@example.com']]], $parser->only('note.headers.to', 'note.headers.from'));
        $this->assertEquals(['id' => 123, 'status' => null, 'note' => ['body' => 'Hello World']], $parser->only('note.body', 'id', 'status'));
    }

    public function test_return_value_for_selected_key_use_default_if_not_found()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"status":false, "code":123, "note":"", "message":"hello world"}');

        $this->assertEquals('ape', $parser->get('banana', 'ape'));
        $this->assertEquals('123', $parser->get('code', '2345234'));
        $this->assertEquals('abcdef', $parser->get('note', 'abcdef'));
        $this->assertEquals('hello world', $parser->get('message'));
    }

    public function test_return_boolean_value_if_getPayload_has_keys()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->times(3)
            ->andReturn('{"status":false, "code":123, "note":"", "message":"hello world"}');

        $this->assertTrue($parser->has('status', 'code'));
        $this->assertFalse($parser->has('banana'));
        $this->assertFalse($parser->has('note'));
    }

    public function test_only_return_selected_fields()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->andReturn('{"status":123, "message":"hello world"}');

        $this->assertEquals(['status' => 123], $parser->only('status'));
    }

    public function test_except_do_not_return_selected_fields()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getPayload')
            ->twice()
            ->andReturn('{"status":123, "message":"hello world"}');

        $this->assertEquals(['status' => 123], $parser->except('message'));
        $this->assertEquals(['status' => 123, 'message' => 'hello world'], $parser->except('message.tags'));
    }

    public function test_format_detection_defaults_to_json()
    {
        $parser = new Parser();

        $_SERVER['HTTP_CONTENT_TYPE'] = "somerandomstuff";
        $this->assertEquals('Nathanmac\Utilities\Parser\Formats\JSON', $parser->getFormatClass());

        $_SERVER['CONTENT_TYPE'] = "somerandomstuff";
        $this->assertEquals('Nathanmac\Utilities\Parser\Formats\JSON', $parser->getFormatClass());
    }

    public function test_throw_an_exception_when_parsed_auto_detect_mismatch_content_type()
    {
        $parser = m::mock('Nathanmac\Utilities\Parser\Parser')
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $parser->shouldReceive('getFormatClass')
            ->once()
            ->andReturn('Nathanmac\Utilities\Parser\Formats\Serialize');

        $parser->shouldReceive('getPayload')
            ->once()
            ->andReturn("<?xml version=\"1.0\" encoding=\"UTF-8\"?><xml><status>123</status><message>hello world</message></xml>");

        $this->expectException('Exception');
        $this->expectExceptionMessage('Failed To Parse Serialized Data');
        $parser->payload();
        // $this->assertEquals(['status' => 123, 'message' => 'hello world'], $parser->payload());
    }

    public function test_can_register_format_classes()
    {
        // For some reason this won't autoload...
        require_once(__DIR__ . '/CustomFormatter.php');

        $parser = new Parser();

        $_SERVER['CONTENT_TYPE'] = "application/x-custom-format";
        $this->assertEquals('Nathanmac\Utilities\Parser\Formats\JSON', $parser->getFormatClass());

        $parser->registerFormat('application/x-custom-format', 'Nathanmac\Utilities\Parser\Tests\CustomFormatter');
        $this->assertEquals('Nathanmac\Utilities\Parser\Tests\CustomFormatter', $parser->getFormatClass());
    }
}
