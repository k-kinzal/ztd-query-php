<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Begin::class)]
#[Medium]
final class BeginTest extends TestCase
{
    public function testRenderWritesBegin(): void
    {
        self::assertSame('BEGIN', (new Inspection())->inspect('begin work')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection('mysql-5.6.51'))->inspect('BEGIN');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
