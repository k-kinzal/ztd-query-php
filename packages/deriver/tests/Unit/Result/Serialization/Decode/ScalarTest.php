<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Serialization\Decode;

use Deriver\Exception\InvalidInputException;
use Deriver\Result\Serialization\Decode\Scalar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Result\Serialization\Decode\Scalar
 */
#[CoversClass(Scalar::class)]
#[UsesClass(\Deriver\Result\Serialization\Decode\Fields::class)]
#[Small]
final class ScalarTest extends TestCase
{
    public function testReadPreservesNullAndFalseWithoutCoercion(): void
    {
        $decoder = new Scalar();
        self::assertNull($decoder->read((object)['type' => 'null','value' => null]));
        self::assertSame(false, $decoder->read((object)['type' => 'bool','value' => false]));
    }
    public function testBytesRejectsNoncanonicalBase64Padding(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Scalar())->bytes('YQ');
    }
    public function testIntegerRejectsValuesAboveTheTargetMaximum(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Scalar())->integer('9223372036854775808');
    }
    public function testFloatingPreservesNonfiniteBits(): void
    {
        $decoder = new Scalar();
        self::assertTrue(is_nan($decoder->floating('7ff8000000000001')));
        self::assertSame(INF, $decoder->floating('7ff0000000000000'));
        self::assertSame(-INF, $decoder->floating('fff0000000000000'));
    }
}
