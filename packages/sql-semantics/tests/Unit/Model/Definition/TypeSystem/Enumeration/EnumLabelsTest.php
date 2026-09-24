<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabels;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(EnumLabels::class)]
final class EnumLabelsTest extends TestCase
{
    public function testLabelAcceptsSixtyThreeBytes(): void
    {
        EnumLabels::label(str_repeat('a', 63));
        EnumLabels::label('');
        $this->expectException(InvalidStructure::class);
        EnumLabels::label(str_repeat('é', 32));
    }

    public function testLabelsRejectsARepeatedLabel(): void
    {
        EnumLabels::labels(['1', '01']);
        $this->expectException(InvalidStructure::class);
        EnumLabels::labels(['a', 'b', 'a']);
    }
}
