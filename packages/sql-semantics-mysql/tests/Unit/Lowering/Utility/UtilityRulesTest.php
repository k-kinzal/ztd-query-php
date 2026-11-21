<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\UtilityRules;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(UtilityRules::class)]
#[Medium]
final class UtilityRulesTest extends TestCase
{
    public function testStatementDispatchesEveryStatementRule(): void
    {
        self::assertSame('SHOW DATABASES', (new Semantics('mysql-5.6.51'))->inspect('show databases')->toString());
        self::assertSame('SHOW PLUGINS', (new Semantics())->inspect('show plugins')->toString());
        self::assertSame('SET @a = 1', (new Semantics())->inspect('set @a = 1')->toString());
        self::assertSame('HELP x', (new Semantics())->inspect('help x')->toString());
    }

    public function testBinaryLogsWordAcceptsBothKeywords(): void
    {
        self::assertSame('SHOW BINARY LOGS', (new Semantics('mysql-5.6.51'))->inspect('show master logs')->toString());
        self::assertSame('SHOW BINARY LOGS', (new Semantics('mysql-8.3.0'))->inspect('show master logs')->toString());
    }
}
