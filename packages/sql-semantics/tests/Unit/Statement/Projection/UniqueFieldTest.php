<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\UniqueField;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(UniqueField::class)]
#[Small]
final class UniqueFieldTest extends TestCase
{
    public function testRetainsTheOutputPositionAndFieldIdentity(): void
    {
        $field = new Field(new NullConstant());
        $result = new UniqueField(2, $field);
        self::assertSame(2, $result->position);
        self::assertSame($field, $result->field);
    }

    public function testRejectsANegativePosition(): void
    {
        $this->expectException(InvalidConstruction::class);
        new UniqueField(-1, new Field(new NullConstant()));
    }
}
