<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Kill::class)]
#[Medium]
final class KillTest extends TestCase
{
    public function testRenderWritesTheScopeAndTheIdentifier(): void
    {
        self::assertSame('KILL CONNECTION 1 + 2', (new Inspection('mysql-5.6.51'))->inspect('kill connection 1 + 2')->toString());
    }

    public function testDeriveStatementDerivesTheIdentifier(): void
    {
        $kill = (new Inspection())->inspect('KILL 42');
        self::assertInstanceOf(Kill::class, $kill->statement);

        self::assertInstanceOf(Known::class, $kill->facts->scalar($kill->statement->process)->type);
    }
}
