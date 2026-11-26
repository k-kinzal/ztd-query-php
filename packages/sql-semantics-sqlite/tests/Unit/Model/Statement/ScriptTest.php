<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Lexical;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Script;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Script::class)]
#[Medium]
final class ScriptTest extends TestCase
{
    public function testDeriveStatementDerivesEveryMemberAgainstTheSameContext(): void
    {
        $operation = (new Inspection())->diagnose('CREATE TABLE t (a INTEGER); INSERT INTO t (a) VALUES (1)', []);

        self::assertInstanceOf(Script::class, $operation->statement);
        self::assertCount(2, $operation->statement->statements);
        self::assertCount(1, $operation->declarations());
        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
    }

    public function testDeriveStatementCollectsTheDeclarationsOfEveryMember(): void
    {
        $operation = (new Inspection())->diagnose('CREATE TABLE a (x INTEGER); CREATE TABLE b (y TEXT)');

        self::assertSame(['a', 'b'], array_map(static fn ($table): string => $table->name->name->value, $operation->declarations()));
    }

    public function testDeriveStatementOfAnEmptyInputHasNoMembers(): void
    {
        $semantics = new Inspection();

        $operation = $semantics->diagnose('');

        self::assertInstanceOf(Script::class, $operation->statement);
        self::assertSame([], $operation->statement->statements);
        self::assertSame('', $operation->toString());
        self::assertNull($operation->shape());
    }

    public function testRenderSeparatesTheMembersWithTerminators(): void
    {
        $semantics = new Inspection();
        $output = new Output(Platform::shared()->codec($semantics->profile()));
        $operation = $semantics->diagnose('select 1 ; delete from t ;');

        $operation->statement->render($output);

        self::assertSame('SELECT 1; DELETE FROM t', (new Lexical())->join($output->pieces()));
        self::assertSame('SELECT 1; DELETE FROM t', $operation->toString());
    }

    public function testRenderIsNotReachedForAScriptOfExactlyOneStatement(): void
    {
        $statement = (new Inspection())->diagnose('SELECT 1')->statement;

        $this->expectExceptionMessage('A script holds no statement or several; one statement is its own root.');

        new Script([$statement]);
    }

    public function testRenderIsNotReachedForANestedScript(): void
    {
        $script = (new Inspection())->diagnose('DELETE FROM t; DELETE FROM u')->statement;

        self::assertInstanceOf(Script::class, $script);
        $this->expectExceptionMessage('A script does not nest.');

        new Script([$script, $script]);
    }
}
