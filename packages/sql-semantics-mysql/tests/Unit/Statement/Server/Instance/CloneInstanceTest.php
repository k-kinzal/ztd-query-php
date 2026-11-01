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
    public function testValueRejectsAnUnquotedPassword(): void
    {
        $this->expectExceptionMessage('A clone password is quoted text.');

        new CloneInstance(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3306'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('10', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit));
    }

    public function testValueRejectsAnUnquotedDirectory(): void
    {
        $this->expectExceptionMessage('A clone directory is quoted text.');

        new CloneInstance(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3306'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('pw'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('2', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testRenderGluesTheAccountColonAndPort(): void
    {
        self::assertSame("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' REQUIRE SSL", (new Semantics(Dialect::MySql))->analyze("clone instance from 'u'@'h':3306 identified by 'p' require ssl")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' DATA DIRECTORY = '/d'");

        self::assertSame([], $operation->diagnostics());
        self::assertNull($operation->output());
    }
}
