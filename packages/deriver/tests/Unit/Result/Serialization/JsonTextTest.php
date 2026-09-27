<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Serialization;

use Deriver\Exception\InvalidInputException;
use Deriver\Result\Serialization\JsonText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \Deriver\Result\Serialization\JsonText
 */
#[CoversClass(JsonText::class)]
#[Small]
final class JsonTextTest extends TestCase
{
    public function testEncodeReservesItsPrefixWithoutAmbiguity(): void
    {
        $codec = new JsonText();
        self::assertSame('ascii', $codec->encode('ascii'));
        self::assertSame('~b64~' . base64_encode('~b64~text'), $codec->encode('~b64~text'));
    }
    public function testDecodePreservesNonUtf8Bytes(): void
    {
        $codec = new JsonText();
        self::assertSame("\xff\0", $codec->decode($codec->encode("\xff\0")));
    }
    public function testDecodeRejectsMalformedBase64Tags(): void
    {
        $this->expectException(InvalidInputException::class);
        (new JsonText())->decode('~b64~!');
    }
    public function testTreePreservesObjectsAndListOrder(): void
    {
        $codec = new JsonText();
        $record = (object) ["\xff" => [1, true, null]];
        $encoded = $codec->tree($record);
        self::assertEquals((object) ['~b64~/w==' => [1, true, null]], $encoded);
    }
    public function testTreePreservesNumericObjectPropertyNames(): void
    {
        $input = new stdClass();
        $input->{'0'} = 'zero';
        $output = (new JsonText())->tree($input);
        self::assertInstanceOf(stdClass::class, $output);
        self::assertSame('zero', $output->{'0'});
    }
}
