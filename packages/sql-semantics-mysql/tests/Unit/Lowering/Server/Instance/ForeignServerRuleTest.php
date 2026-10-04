<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\ForeignServerRule;

#[CoversClass(ForeignServerRule::class)]
#[Medium]
final class ForeignServerRuleTest extends TestCase
{
    public function testStatementLowersAlter(): void
    {
        self::assertSame("ALTER SERVER s OPTIONS (HOST 'h')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("alter server s options (host 'h')")->toString());
    }

    public function testCreateLowersNameAndWrapper(): void
    {
        self::assertSame("CREATE SERVER s FOREIGN DATA WRAPPER w OPTIONS (USER 'u')", (new Semantics(Dialect::MySql))->analyze("create server 's' foreign data wrapper w options (user 'u')")->toString());
    }

    public function testOptionsLowersThePort(): void
    {
        self::assertSame('CREATE SERVER s FOREIGN DATA WRAPPER w OPTIONS (PORT 3306)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('create server s foreign data wrapper w options (port 3306)')->toString());
    }
}
