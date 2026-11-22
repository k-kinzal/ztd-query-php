<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AbsentField;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(AbsentField::class)]
#[Medium]
final class AbsentFieldTest extends TestCase
{
    public function testNameIsTheLookedUpNameOfACompleteShape(): void
    {
        $lookup = (new Inspection())->inspect('SELECT 1 AS a')->lookupField('b');

        self::assertInstanceOf(AbsentField::class, $lookup);
        self::assertSame('b', $lookup->name);
    }

    public function testNameOfAPositionWithoutNameIsNeverFound(): void
    {
        $lookup = (new Inspection())->inspect('SELECT 1')->lookupField('');

        self::assertInstanceOf(AbsentField::class, $lookup);
    }
}
