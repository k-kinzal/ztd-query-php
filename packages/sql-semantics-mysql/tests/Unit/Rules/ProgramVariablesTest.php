<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\ProgramVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ProgramVariables::class)]
#[Medium]
final class ProgramVariablesTest extends TestCase
{
    public function testFindPrefersAVariableOfTheProgramToAColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE a INT; SELECT a FROM t; UPDATE t SET a = a; END', [$table]);
        $create = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $item);
        $resolution = $operation->facts->scalar($item->expression)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertInstanceOf(VariableDeclaration::class, $resolution->relation);
    }

    public function testFindReadsTheVariablesARunningStatementIsGiven(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $relation = new ParameterList([]);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), program: [new ProgramRow($relation, [new Name('x')], [Domain::integer()])]);
        $environment = new Environment($semantics->context(null, true, null, $settings));

        $found = (new ProgramVariables())->find($environment, new Name('X'));
        $written = (new ProgramVariables())->find(new Environment($semantics->context(null, true, null, $settings), null, [], [], [], true), new Name('x'));

        self::assertInstanceOf(ResolvedColumn::class, $found);
        self::assertSame([$relation, null], [$found->relation, $written]);
    }

    public function testFieldFindsAColumnOfTheNewRowOfARunningTrigger(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $relation = new ParameterList([]);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), program: [new ProgramRow($relation, [new Name('a')], [Domain::integer()], new Name('NEW'))]);
        $environment = new Environment($semantics->context(null, true, null, $settings));

        self::assertInstanceOf(ResolvedColumn::class, (new ProgramVariables())->field($environment, new Name('a'), new QualifiedName(new Name('new'))));
        self::assertInstanceOf(MissingColumn::class, (new ProgramVariables())->field($environment, new Name('b'), new QualifiedName(new Name('NEW'))));
        self::assertNull((new ProgramVariables())->field($environment, new Name('a'), new QualifiedName(new Name('OLD'))));
    }

    public function testResolvedTypesTheNameAsDeclaredAndNullable(): void
    {
        $resolved = (new ProgramVariables())->resolved(new ProgramRow(new ParameterList([]), [new Name('x')], [Domain::integer()]), 0);

        self::assertSame(['x', \SqlSemantics\Statement\Type\Nullability::Nullable], [$resolved->slot->name?->value, $resolved->slot->nullability]);
    }
}
