<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\UniqueField;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(UniqueField::class)]
#[Medium]
final class UniqueFieldTest extends TestCase
{
    public function testFieldIsTheOnlyFieldWithTheName(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 AS a, 2 AS b');

        $lookup = $operation->lookupField('b');

        self::assertInstanceOf(UniqueField::class, $lookup);
        self::assertSame($operation->field(1), $lookup->field);
        self::assertSame(1, $lookup->field->position);
    }
}
