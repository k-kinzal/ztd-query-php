<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\SelectionUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(SelectionUse::class)]
#[Medium]
final class SelectionUseTest extends TestCase
{
    public function testClausesPrepareTheWhereDependencyBeforeTheHavingRow(): void
    {
        $session = (new Instance())->connect();
        $selection = new DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('1'))], where: new ColumnUse(new Name('missing')), having: new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'where clause'");
        (new SelectionUse())->raise($selection, $session);
    }

    public function testOperandResolvesAnOutputAliasBeforeTheOrderingRowCondition(): void
    {
        $session = (new Instance())->connect();
        $ordering = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem(new Row([new ColumnUse(new Name('v')), new NumberLiteral('2')]));
        $selection = new DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('7'), new Name('v'))], orderBy: [$ordering]);
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new SelectionUse())->raise($selection, $session);
    }

    public function testRaiseBindsTheRetainedTableAndColumnWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(7)');
        $selection = new DeferredSelection([new DeferredProjection(new Row([new ColumnUse(new Name('a')), new NumberLiteral('2')]))], new TableReference(new QualifiedName(new Name('t'))));
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new SelectionUse())->raise($selection, $session);
    }
}
