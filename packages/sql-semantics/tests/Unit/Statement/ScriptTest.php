<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Script;

#[CoversClass(Script::class)]
#[Medium]
final class ScriptTest extends TestCase
{
    public function testDeriveStatementDerivesEveryMemberAgainstTheSameContext(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER); INSERT INTO t (a) VALUES (1)', []);

        self::assertInstanceOf(Script::class, $operation->statement);
        self::assertCount(2, $operation->statement->statements);
        self::assertCount(1, $operation->declarations());
        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
    }

    public function testDeriveStatementCollectsTheDeclarationsOfEveryMember(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE a (x INTEGER); CREATE TABLE b (y TEXT)');

        self::assertSame(['a', 'b'], array_map(static fn ($table): string => $table->name->name->value, $operation->declarations()));
    }

    public function testDeriveStatementOfAnEmptyInputHasNoMembers(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $operation = $semantics->analyze('');

        self::assertInstanceOf(Script::class, $operation->statement);
        self::assertSame([], $operation->statement->statements);
        self::assertSame('', $operation->toString());
        self::assertNull($operation->shape());
    }

    public function testRenderSeparatesTheMembersWithTerminators(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $output = new Output(Platforms::of('sqlite')->codec($semantics->profile()));
        $operation = $semantics->analyze('select 1 ; delete from t ;');

        $operation->statement->render($output);

        self::assertSame('SELECT 1; DELETE FROM t', (new Lexical())->join($output->pieces()));
        self::assertSame('SELECT 1; DELETE FROM t', $operation->toString());
    }

    public function testRenderIsNotReachedForAScriptOfExactlyOneStatement(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1')->statement;

        $this->expectExceptionMessage('A script holds no statement or several; one statement is its own root.');

        new Script([$statement]);
    }

    public function testRenderIsNotReachedForANestedScript(): void
    {
        $script = (new Semantics(Dialect::Sqlite))->analyze('DELETE FROM t; DELETE FROM u')->statement;

        self::assertInstanceOf(Script::class, $script);
        $this->expectExceptionMessage('A script does not nest.');

        new Script([$script, $script]);
    }
}
