<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Function\FunctionName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(FunctionName::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FunctionNameTest extends TestCase
{
    public function testKeepsTheIdentifierPartsInOrder(): void
    {
        $name = new FunctionName(['app', 'total']);
        self::assertSame(['app', 'total'], $name->parts);
    }

    public function testAcceptsASingleUnqualifiedPart(): void
    {
        self::assertSame(['total'], (new FunctionName(['total']))->parts);
    }

    public function testRejectsEmptyParts(): void
    {
        $this->expectException(InvalidStructure::class);
        new FunctionName([]);
    }
}
