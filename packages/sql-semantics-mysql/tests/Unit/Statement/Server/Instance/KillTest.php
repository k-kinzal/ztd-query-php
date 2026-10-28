<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Kill::class)]
#[Medium]
final class KillTest extends TestCase
{
    public function testDeriveStatementReportsDirectWindowNamesOutsideQueryBlocks(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('KILL SUM(1) OVER missing');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse::class, $operation->diagnostics()[0]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::UnknownWindow, $operation->diagnostics()[0]->rule);
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $operation->diagnostics()[0]->name);
        self::assertSame('missing', $operation->diagnostics()[0]->name->value);
    }

    public function testRenderRetainsTheRejectedTruthComputation(): void
    {
        $kill = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('KILL (USER() = (USER() MEMBER (ROW(USER(), USER())))) IS TRUE');
        self::assertInstanceOf(Kill::class, $kill);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTruthTest::class, $kill->process);
        self::assertSame('KILL (USER() = (USER() MEMBER (ROW(USER(), USER())))) IS TRUE', $kill->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand::class, $kill->diagnostics()[0]);
    }

    public function testRenderWritesTheScopeAndTheIdentifier(): void
    {
        self::assertSame('KILL CONNECTION 1 + 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('kill connection 1 + 2')->toString());
    }

    public function testDeriveStatementDerivesTheIdentifier(): void
    {
        $kill = (new Semantics(Dialect::MySql))->analyze('KILL 42');
        self::assertInstanceOf(Kill::class, $kill);

        self::assertInstanceOf(Known::class, $kill->process->scalarInformation()->type);
    }
}
