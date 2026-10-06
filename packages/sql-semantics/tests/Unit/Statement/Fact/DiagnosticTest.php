<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Diagnostic::class)]
#[Medium]
final class DiagnosticTest extends TestCase
{
    public function testMessageDescribesEachProblemInDerivationOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT b FROM t', []);

        self::assertSame(['Relation t does not exist.', 'Column b does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
    }

    public function testMessageIsNotNeededForAStatementWithoutProblems(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (b INTEGER)');

        self::assertSame([], $semantics->analyze('SELECT b FROM t', [$table])->facts->diagnostics);
    }
}
