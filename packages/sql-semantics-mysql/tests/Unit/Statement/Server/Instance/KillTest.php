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

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::UnknownWindow, $operation->facts->diagnostics[0]->rule);
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $operation->facts->diagnostics[0]->name);
        self::assertSame('missing', $operation->facts->diagnostics[0]->name->value);
    }

    public function testRenderWritesTheScopeAndTheIdentifier(): void
    {
        self::assertSame('KILL CONNECTION 1 + 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('kill connection 1 + 2')->toString());
    }

    public function testDeriveStatementDerivesTheIdentifier(): void
    {
        $kill = (new Semantics(Dialect::MySql))->analyze('KILL 42');
        self::assertInstanceOf(Kill::class, $kill->statement);

        self::assertInstanceOf(Known::class, $kill->facts->scalar($kill->statement->process)->type);
    }
}
