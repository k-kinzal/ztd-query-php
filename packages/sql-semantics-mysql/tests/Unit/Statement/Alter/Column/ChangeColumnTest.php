<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;

#[CoversClass(ChangeColumn::class)]
#[Medium]
final class ChangeColumnTest extends TestCase
{
    public function testChangedAnswersTheRedefinedColumn(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t MODIFY a BIGINT, CHANGE b c INT');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $alter);

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        [$modify, $change] = $alter->statement->commands;
        self::assertInstanceOf(ChangeColumn::class, $modify);
        self::assertInstanceOf(ChangeColumn::class, $change);
        self::assertSame(['a', 'b'], [$modify->changed()->column->value, $change->changed()->column->value]);
    }

    public function testDeriveCommandDerivesTheDefinition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t MODIFY a INT DEFAULT (x)', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $alter);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesChangeOrModify(): void
    {
        self::assertSame('ALTER TABLE t CHANGE a b INT FIRST, MODIFY COLUMN c TEXT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t CHANGE a b INT FIRST, MODIFY COLUMN c TEXT')->toString());
    }
    public function testRejectsADotPrefixThatWouldChangeTheOldNameInterpretation(): void
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('b'), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written);
        $definition = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition($name, new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        $this->expectExceptionMessage('A dot-prefixed CHANGE definition follows a fully qualified old name.');
        new ChangeColumn(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), $definition);
    }

    public function testACompleteOldQualifierSeparatesADotPrefixedNewName(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $operation = $semantics->analyze('ALTER TABLE d.t CHANGE d.t.a .b INT');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        self::assertSame('ALTER TABLE d.t CHANGE d.t.a .b INT', $operation->toString());
    }

}
