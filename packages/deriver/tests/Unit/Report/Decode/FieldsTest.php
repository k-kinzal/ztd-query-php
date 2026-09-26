<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Decode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Report\Decode\Fields
 */
#[CoversClass(\Deriver\Report\Decode\Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testTextRejectsANonStringValue(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Report\Decode\Fields())->text((object)['value' => 1], 'value');
    }
    public function testBooleanPreservesFalseWithoutTreatingItAsMissing(): void
    {
        self::assertFalse((new \Deriver\Report\Decode\Fields())->boolean((object)['value' => false], 'value'));
    }
    public function testObjectRejectsAJsonArray(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Report\Decode\Fields())->object((object)['value' => []], 'value');
    }
    public function testObjectsPreservesRecordOrder(): void
    {
        $a = (object)['id' => 'a'];
        $b = (object)['id' => 'b'];
        self::assertSame([$a,$b], (new \Deriver\Report\Decode\Fields())->objects((object)['items' => [$a,$b]], 'items'));
    }
}
