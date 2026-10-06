<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $truncate = $semantics->analyze('TRUNCATE t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $truncate->facts->relation($truncate->statement)->table);
    }

    public function testRenderWritesTable(): void
    {
        self::assertSame('TRUNCATE TABLE db.t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('truncate db.t')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: TRUNCATE TABLE reports that the table doesn\'t exist.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('TRUNCATE TABLE v', [$table, $view])->facts->diagnostics));
    }
}
