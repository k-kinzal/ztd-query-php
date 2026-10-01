<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Serialization\Decode;

use Deriver\Exception\InvalidInputException;
use Deriver\Result\Serialization\Decode\Fields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Result\Serialization\Decode\Fields
 */
#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testTextRejectsANonStringValue(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Fields())->text((object)['value' => 1], 'value');
    }
    public function testBooleanPreservesFalseWithoutTreatingItAsMissing(): void
    {
        self::assertFalse((new Fields())->boolean((object)['value' => false], 'value'));
    }
    public function testObjectRejectsAJsonArray(): void
    {
        $this->expectException(InvalidInputException::class);
        (new Fields())->object((object)['value' => []], 'value');
    }
    public function testObjectsPreservesRecordOrder(): void
    {
        $a = (object)['id' => 'a'];
        $b = (object)['id' => 'b'];
        self::assertSame([$a,$b], (new Fields())->objects((object)['items' => [$a,$b]], 'items'));
    }
}
