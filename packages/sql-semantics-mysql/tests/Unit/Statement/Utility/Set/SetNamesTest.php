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
    public function testDeriveItemDerivesNothing(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET NAMES DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(SetNames::class, $set->statement->items[0]);
        self::assertNull($set->statement->items[0]->charset->name);
        self::assertSame([], $set->facts->diagnostics);
    }

    public function testRenderWritesTheCollation(): void
    {
        self::assertSame('SET NAMES utf8 COLLATE DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set names 'utf8' collate default")->toString());
    }
}
