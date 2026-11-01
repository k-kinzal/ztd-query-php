<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;

#[CoversClass(Detach::class)]
#[Medium]
final class DetachTest extends TestCase
{
    public function testDeriveStatementDerivesTheOperand(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('DETACH aux', []);
        $statement = $operation;

        self::assertInstanceOf(Detach::class, $statement);
        self::assertTrue($statement->schema->hasBinding());
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('detach database aux');

        self::assertInstanceOf(Detach::class, $operation);
        self::assertInstanceOf(NameOperand::class, $operation->schema);
        self::assertSame('DETACH aux', $operation->toString());
    }
}
