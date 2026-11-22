<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('TRUNCATE t, u', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable::class, $n1);
        $n2 = $statement->facts->relation($n1->tables[1])->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\MissingTable::class, $n2);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Model\\Statement\\Reference\\Table\\MissingTable', $n2::class);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('TRUNCATE TABLE ONLY t, s.u CONTINUE IDENTITY RESTRICT', []);
        self::assertSame('TRUNCATE ONLY t, s.u CONTINUE IDENTITY RESTRICT', $statement->toString());
    }

    public function testDeriveStatementReportsANonTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [$semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a'), $semantics->diagnose('CREATE FOREIGN TABLE f (a int) SERVER x')];
        self::assertSame(['"v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('TRUNCATE v, f', $context)->facts->diagnostics));
    }
}
