<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\FlushRule;

#[CoversClass(FlushRule::class)]
#[Medium]
final class FlushRuleTest extends TestCase
{
    public function testStatementLowersTheItems(): void
    {
        self::assertSame('FLUSH LOGS, STATUS', (new Semantics(Dialect::MySql))->analyze('flush logs, status')->toString());
    }

    public function testTablesRejectsForExportWithoutTables(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql))->analyze('FLUSH TABLES FOR EXPORT');
    }

    public function testItemsLowersEveryOption(): void
    {
        self::assertSame('FLUSH HOSTS, QUERY CACHE, DES_KEY_FILE, USER_RESOURCES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('flush hosts, query cache, des_key_file, user_resources')->toString());
    }
}
