<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;

#[CoversClass(Savepoint::class)]
#[Medium]
final class SavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('SAVEPOINT s', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('savepoint s')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SAVEPOINT s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
