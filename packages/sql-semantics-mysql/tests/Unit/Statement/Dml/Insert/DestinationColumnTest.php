<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(DestinationColumn::class)]
#[Medium]
final class DestinationColumnTest extends TestCase
{
    public function testRejectsAColumnFromAnotherDeclaration(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $left = $semantics->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $right = $semantics->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $this->expectExceptionMessage('A resolved destination belongs to its declared table.');
        new DestinationColumn(new Name('a'), new DeclaredTable($left), $right->columns[0]);
    }

    public function testRejectsANameThatDoesNotDenoteTheColumn(): void
    {
        $table = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $this->expectExceptionMessage('The destination name denotes its declared column.');
        new DestinationColumn(new Name('b'), new DeclaredTable($table), $table->columns[0]);
    }

    public function testRenderPreservesTheWrittenColumnCaseAndReference(): void
    {
        $table = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $column = new DestinationColumn(new Name('A'), new DeclaredTable($table), $table->columns[0]);
        $out = new Output(new Codec(GrammarRelease::MySql847));
        $column->render($out);
        self::assertSame('A', (new Lexical())->join($out->pieces()));
        self::assertSame($table->columns[0], $column->declaration);
    }
}
