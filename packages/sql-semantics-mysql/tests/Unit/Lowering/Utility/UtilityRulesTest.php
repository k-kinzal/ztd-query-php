<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\UtilityRules;

#[CoversClass(UtilityRules::class)]
#[Medium]
final class UtilityRulesTest extends TestCase
{
    public function testStatementDispatchesEveryStatementRule(): void
    {
        self::assertSame('SHOW DATABASES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show databases')->toString());
        self::assertSame('SHOW PLUGINS', (new Semantics(Dialect::MySql))->analyze('show plugins')->toString());
        self::assertSame('SET @a = 1', (new Semantics(Dialect::MySql))->analyze('set @a = 1')->toString());
        self::assertSame('HELP x', (new Semantics(Dialect::MySql))->analyze('help x')->toString());
    }

    public function testBinaryLogsWordAcceptsBothKeywords(): void
    {
        self::assertSame('SHOW BINARY LOGS', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show master logs')->toString());
        self::assertSame('SHOW BINARY LOGS', (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('show master logs')->toString());
    }
}
