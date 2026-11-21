<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(Diagnostic::class)]
#[Medium]
final class DiagnosticTest extends TestCase
{
    public function testMessageDescribesEachProblemInDerivationOrder(): void
    {
        $operation = (new Semantics())->inspect('SELECT b FROM t', []);

        self::assertSame(['Relation t does not exist.', 'Column b does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
    }

    public function testMessageIsNotNeededForAStatementWithoutProblems(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (b INTEGER)');

        self::assertSame([], $semantics->inspect('SELECT b FROM t', [$table])->facts->diagnostics);
    }
}
