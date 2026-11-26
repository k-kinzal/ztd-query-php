<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Dml\WriteScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(WriteScope::class)]
#[Medium]
final class WriteScopeTest extends TestCase
{
    public function testFieldTakesTheColumnFact(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('UPDATE t SET b = 1', [$t]);
        self::assertInstanceOf(Update::class, $operation->statement);
        $column = $operation->statement->assignments[0]->column;
        $field = (new WriteScope())->field(3, $column, $operation->facts->scalar($column));

        self::assertSame(3, $field->position);
        self::assertSame('b', $field->column()?->name->value);
    }

    public function testValueDerivesDefaultAsTheColumn(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('UPDATE t SET a = DEFAULT', [$t]);
        self::assertInstanceOf(Update::class, $operation->statement);

        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->assignments[0]->value)->nullability);
    }

    public function testValueReportsARowAsTheWrittenValue(): void
    {
        $operation = (new Inspection())->diagnose('UPDATE t SET a = (1, 2)');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testAssignAnswersTheAssignedColumns(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t SET b = 1, z = 2', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDistinctReportsAColumnWrittenTwice(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t (b, t.b) VALUES (1, 2)', [$t]);

        self::assertInstanceOf(DuplicateColumn::class, $operation->facts->diagnostics[0]);
    }
}
