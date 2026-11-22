<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(BareName::class)]
#[Medium]
final class BareNameTest extends TestCase
{
    public function testDeriveScalarIsTextForASystemVariable(): void
    {
        $set = (new Inspection())->diagnose('SET @@sql_mode = a.b');
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
        $set = (new Inspection())->diagnose('SET x = y');
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
        self::assertSame('SET @@x = a.b.c', (new Inspection('mysql-5.7.44'))->diagnose('set @@x = a.b.c')->toString());
    }

    public function testDeriveScalarResolvesARowOfATriggerAsAColumn(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (b INT)');
        $known = $semantics->diagnose('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.b = new.b', [$table]);
        $missing = $semantics->diagnose('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.b = NEW.zz', [$table]);

        self::assertSame([], $known->facts->diagnostics);
        self::assertCount(1, $missing->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $missing->facts->diagnostics[0]);
        self::assertSame('zz', $missing->facts->diagnostics[0]->name->value);
    }
}
