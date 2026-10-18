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
    public function testDiagnosedReportsGrammarConditionsBeforeQueryPreparation(): void
    {
        $facts = new \SqlSemantics\Statement\Fact\Facts([], [], [], [], null, [new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::EmptyValuesRow), new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('nosuch')]);
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1193);
        (new SelectionUse())->diagnosed($facts, $session);
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
