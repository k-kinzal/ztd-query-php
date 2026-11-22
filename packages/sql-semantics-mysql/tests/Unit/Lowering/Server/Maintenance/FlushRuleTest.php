<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\FlushRule;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FlushRule::class)]
#[Medium]
final class FlushRuleTest extends TestCase
{
    public function testStatementLowersTheItems(): void
    {
        self::assertSame('FLUSH LOGS, STATUS', (new Inspection())->inspect('flush logs, status')->toString());
    }

    public function testTablesRejectsForExportWithoutTables(): void
    {
        $this->expectException(AnalysisException::class);

        (new Inspection())->inspect('FLUSH TABLES FOR EXPORT');
    }

    public function testItemsLowersEveryOption(): void
    {
        self::assertSame('FLUSH HOSTS, QUERY CACHE, DES_KEY_FILE, USER_RESOURCES', (new Inspection('mysql-5.6.51'))->inspect('flush hosts, query cache, des_key_file, user_resources')->toString());
    }
}
