<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\FunctionShapes::class)]
#[Small]
final class FunctionShapesTest extends TestCase
{
    public function testDeriveIsOpenForAnUndeclaredFunction(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM f()');
        self::assertNull($query->fields());
    }

    public function testFunctionNameIsTheCalledName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM f() AS (a integer)');
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $from = $select->from;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable::class, $from);
        self::assertSame('f', (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\FunctionShapes())->functionName($from->functions[0])?->value);
    }

    public function testDefinedHasTheDefinedTypes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM f() AS (a boolean)');
        self::assertSame('boolean', ($query->field(0)->type instanceof \SqlSemantics\Statement\Type\Known ? $query->field(0)->type->descriptor->name() : null));
    }

    public function testPaddedMakesSlotsNullable(): void
    {
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\FunctionShapes())->padded([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)])[0]->nullability);
    }

    public function testDistinctRemovesRepeatedInputs(): void
    {
        $missing = new \SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')));
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\FunctionShapes())->distinct([$missing, $missing]));
    }
}
