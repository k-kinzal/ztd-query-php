<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReleaseSavepoint::class)]
#[Medium]
final class ReleaseSavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('RELEASE SAVEPOINT s', (new Inspection())->inspect('release savepoint s')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('RELEASE SAVEPOINT s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
