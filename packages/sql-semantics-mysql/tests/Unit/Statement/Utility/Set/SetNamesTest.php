<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;

#[CoversClass(SetNames::class)]
#[Medium]
final class SetNamesTest extends TestCase
{
    public function testExplicitNamesRetainTheirOrderedNotices(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET NAMES utf8 COLLATE utf8_bin');

        self::assertCount(2, $set->warnings());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning::class, $set->warnings()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning::class, $set->warnings()[1]);
        self::assertSame([3719, 3778], [$set->warnings()[0]->code(), $set->warnings()[1]->code()]);
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('SET CHARACTER SET utf8')->warnings());
    }

    public function testDeriveItemDerivesNothing(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET NAMES DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set);
        self::assertInstanceOf(SetNames::class, $set->items[0]);
        self::assertNull($set->items[0]->charset->name);
        self::assertSame([], $set->diagnostics());
    }

    public function testRenderWritesTheCollation(): void
    {
        self::assertSame('SET NAMES utf8 COLLATE DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set names 'utf8' collate default")->toString());
    }
}
