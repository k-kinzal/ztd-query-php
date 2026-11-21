<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;

#[CoversClass(TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT)');
        $truncate = $semantics->inspect('TRUNCATE t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $truncate->facts->relation($truncate->statement)->table);
    }

    public function testRenderWritesTable(): void
    {
        self::assertSame('TRUNCATE TABLE db.t', (new Semantics('mysql-5.6.51'))->inspect('truncate db.t')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT)');
        $view = $semantics->inspect('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: TRUNCATE TABLE reports that the table doesn\'t exist.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->inspect('TRUNCATE TABLE v', [$table, $view])->facts->diagnostics));
    }
}
