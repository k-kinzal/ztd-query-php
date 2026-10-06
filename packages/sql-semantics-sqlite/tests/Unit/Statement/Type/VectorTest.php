<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Vector::class)]
#[Medium]
final class VectorTest extends TestCase
{
    public function testNameIncludesTheWidth(): void
    {
        self::assertSame('ROW(2)', (new Vector(2))->name());
        self::assertSame('ROW(10)', (new Vector(10))->name());
        self::assertSame(3, (new Vector(3))->width);
    }

    public function testNameDescribesARowValueAndAMultiColumnSubquery(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) = (1, 2), (SELECT 1, 2, 3) = (1, 2, 3)', []);
        $pair = $query->field(0)->expression;
        $triple = $query->field(1)->expression;

        self::assertInstanceOf(Binary::class, $pair);
        self::assertInstanceOf(Known::class, $query->facts->scalar($pair->left)->type);
        self::assertInstanceOf(Vector::class, $query->facts->scalar($pair->left)->type->descriptor);
        self::assertSame('ROW(2)', $query->facts->scalar($pair->left)->type->descriptor->name());
        self::assertInstanceOf(Binary::class, $triple);
        self::assertInstanceOf(Known::class, $query->facts->scalar($triple->left)->type);
        self::assertInstanceOf(Vector::class, $query->facts->scalar($triple->left)->type->descriptor);
        self::assertSame(3, $query->facts->scalar($triple->left)->type->descriptor->width);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testRefusesASingleValue(): void
    {
        $this->expectExceptionMessage('A row value has at least two values.');

        new Vector(1);
    }
}
