<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\HandlerRule;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(HandlerRule::class)]
#[Medium]
final class HandlerRuleTest extends TestCase
{
    public function testStatementLowersOpenAndClose(): void
    {
        self::assertSame('HANDLER t OPEN AS h', (new Semantics('mysql-5.6.51'))->analyze('handler t open as h')->toString());
        self::assertSame('HANDLER h CLOSE', (new Semantics())->analyze('handler h close')->toString());
    }

    public function testReadLowersScans(): void
    {
        self::assertSame('HANDLER h READ FIRST WHERE a = 1', (new Semantics('mysql-5.7.44'))->analyze('handler h read first where a = 1')->toString());
        self::assertSame('HANDLER h READ NEXT LIMIT 1', (new Semantics())->analyze('handler h read next limit 1')->toString());
    }

    public function testIndexLowersReadsAndSeeks(): void
    {
        self::assertSame('HANDLER h READ i LAST', (new Semantics('mysql-5.6.51'))->analyze('handler h read i last')->toString());
        self::assertSame('HANDLER h READ i <= (1, 2)', (new Semantics())->analyze('handler h read i <= (1, 2)')->toString());
        self::assertSame('HANDLER h READ i > (1)', (new Semantics('mysql-5.7.44'))->analyze('handler h read i > (1)')->toString());
    }
}
