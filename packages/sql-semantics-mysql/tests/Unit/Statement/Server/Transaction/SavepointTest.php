<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Savepoint::class)]
#[Medium]
final class SavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('SAVEPOINT s', (new Inspection('mysql-5.6.51'))->inspect('savepoint s')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('SAVEPOINT s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
