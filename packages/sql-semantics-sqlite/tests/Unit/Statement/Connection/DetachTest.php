<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Detach::class)]
#[Medium]
final class DetachTest extends TestCase
{
    public function testDeriveStatementDerivesTheOperand(): void
    {
        $operation = (new Inspection())->inspect('DETACH aux', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Detach::class, $statement);
        self::assertTrue($operation->facts->covers($statement->schema));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Inspection())->inspect('detach database aux');

        self::assertInstanceOf(Detach::class, $operation->statement);
        self::assertInstanceOf(NameOperand::class, $operation->statement->schema);
        self::assertSame('DETACH aux', $operation->toString());
    }
}
