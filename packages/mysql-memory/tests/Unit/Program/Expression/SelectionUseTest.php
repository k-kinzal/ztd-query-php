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
    public function testRaisePreservesRepeatedViewRelationsBeforeTheOperandCondition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $selection = new DeferredSelection([new DeferredProjection(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))], new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([new TableReference(new QualifiedName(new Name('t'))), new TableReference(new QualifiedName(new Name('t')))]));
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new SelectionUse())->raise($selection, $session, false, true);
    }

    public function testDiagnosedReportsGrammarConditionsBeforeQueryPreparation(): void
    {
        $facts = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: null, diagnostics: [new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::EmptyValuesRow), new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('nosuch')]);
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
    public function testDiagnosedReportsAssignmentNoticesAfterRelationBinding(): void
    {
        $session = (new Instance())->connect();
        $notice = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AssignmentInExpression);
        $facts = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: null, diagnostics: [], warnings: [$notice]);
        (new SelectionUse())->diagnosed($facts, $session);
        (new SelectionUse())->diagnosed($facts, $session, readingNotices: false);
        self::assertSame([['Warning', 1287, $notice->message()]], $session->diagnostics->conditions);
    }
}
