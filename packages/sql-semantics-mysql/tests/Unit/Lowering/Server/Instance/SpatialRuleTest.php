<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\SpatialRule;

#[CoversClass(SpatialRule::class)]
#[Medium]
final class SpatialRuleTest extends TestCase
{
    public function testStatementLowersOrReplace(): void
    {
        self::assertSame("CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 7 NAME 'n' DEFINITION 'd'", (new Semantics(Dialect::MySql))->analyze("create or replace spatial reference system 7 name 'n' definition 'd'")->toString());
    }

    public function testAttributesKeepsTheWrittenOrder(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM 7 DESCRIPTION 'x' DEFINITION 'd' NAME 'n'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("create spatial reference system 7 description 'x' definition 'd' name 'n'")->toString());
    }
}
