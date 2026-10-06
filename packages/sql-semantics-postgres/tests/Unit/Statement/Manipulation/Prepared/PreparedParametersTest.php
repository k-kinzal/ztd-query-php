<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Prepare;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\PreparedParameters;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PreparedParameters::class)]
#[Medium]
final class PreparedParametersTest extends TestCase
{
    public function testInfersUndeclaredIsTrue(): void
    {
        $prepare = (new Semantics(Dialect::PostgreSql))->analyze('PREPARE p (integer) AS SELECT $1')->statement;
        self::assertInstanceOf(Prepare::class, $prepare);
        self::assertTrue($prepare->parameters?->infersUndeclared());
    }

    public function testDeriveRelationAnswersOneUnnamedSlotPerType(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('PREPARE p (integer, pg_catalog.text) AS SELECT 1');
        $prepare = $operation->statement;
        self::assertInstanceOf(Prepare::class, $prepare);
        self::assertInstanceOf(PreparedParameters::class, $prepare->parameters);
        $slots = $operation->facts->relation($prepare->parameters)->shape->slots;
        self::assertSame([null, null], [$slots[0]->name, $slots[1]->name]);
        self::assertInstanceOf(Known::class, $slots[1]->type);
        self::assertSame(Builtin::Text, $slots[1]->type->descriptor);
        self::assertSame(Nullability::Nullable, $slots[1]->nullability);
    }

    public function testDeriveRelationTypesTheParametersOfThePreparedStatement(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('PREPARE p (integer) AS SELECT $1, $2');
        $prepare = $operation->statement;
        self::assertInstanceOf(Prepare::class, $prepare);
        $projection = $operation->facts->query($prepare->statement)->projection;
        self::assertInstanceOf(Field::class, $projection[0]);
        self::assertInstanceOf(Field::class, $projection[1]);
        self::assertInstanceOf(Known::class, $projection[0]->type);
        self::assertSame(Builtin::Int4, $projection[0]->type->descriptor);
        self::assertInstanceOf(Dependent::class, $projection[1]->type);
        self::assertSame('the value bound to parameter $2', $projection[1]->type->missing[0]->describe());
    }

    public function testRenderWritesTheTypesInParentheses(): void
    {
        self::assertSame('PREPARE p (INTEGER, text) AS SELECT $1', (new Semantics(Dialect::PostgreSql))->analyze('PREPARE p (integer, text) AS SELECT $1')->toString());
    }
}
