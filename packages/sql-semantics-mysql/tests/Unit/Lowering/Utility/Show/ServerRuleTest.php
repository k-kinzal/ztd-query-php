<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ServerRule;

#[CoversClass(ServerRule::class)]
#[Medium]
final class ServerRuleTest extends TestCase
{
    public function testStatementLowersTheServerStatements(): void
    {
        self::assertSame('SHOW GRANTS FOR u USING r, s', (new Semantics(Dialect::MySql))->analyze('show grants for u using r, s')->toString());
        self::assertSame('SHOW ENGINES', (new Semantics(Dialect::MySql))->analyze('show storage engines')->toString());
        self::assertSame('SHOW CHARSET', (new Semantics(Dialect::MySql))->analyze('show character set')->toString());
    }
}
