<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance;

#[CoversClass(CloneInstance::class)]
#[Medium]
final class CloneInstanceTest extends TestCase
{
    public function testRenderGluesTheAccountColonAndPort(): void
    {
        self::assertSame("CLONE INSTANCE FROM u@h:3306 IDENTIFIED BY 'p' REQUIRE SSL", (new Semantics(Dialect::MySql))->analyze("clone instance from 'u'@'h':3306 identified by 'p' require ssl")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' DATA DIRECTORY = '/d'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
