<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BareName::class)]
#[Medium]
final class BareNameTest extends TestCase
{
    public function testDeriveScalarIsTextForASystemVariable(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @@sql_mode = a.b');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        $item = $set->statement->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment::class, $item);
        $value = $item->value;
        self::assertInstanceOf(BareName::class, $value);
        self::assertSame(['a', 'b'], [$value->qualifier?->name->value, $value->word->value]);
        self::assertSame(Nullability::NotNull, $set->facts->scalar($value)->nullability);
    }

    public function testDeriveScalarDependsOnTheProgramOtherwise(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET x = y');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        $item = $set->statement->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment::class, $item);
        $value = $item->value;
        self::assertInstanceOf(BareName::class, $value);
        $type = $set->facts->scalar($value)->type;
        self::assertInstanceOf(Dependent::class, $type);
        self::assertInstanceOf(ProgramVariable::class, $type->missing[0]);
    }

    public function testRenderWritesEveryNamePart(): void
    {
        self::assertSame('SET @@x = a.b.c', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set @@x = a.b.c')->toString());
    }

    public function testDeriveScalarResolvesARowOfATriggerAsAColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (b INT)');
        $known = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.b = new.b', [$table]);
        $missing = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.b = NEW.zz', [$table]);

        self::assertSame([], $known->facts->diagnostics);
        self::assertCount(1, $missing->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $missing->facts->diagnostics[0]);
        self::assertSame('zz', $missing->facts->diagnostics[0]->name->value);
    }
}
