<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(Begin::class)]
#[Medium]
final class BeginTest extends TestCase
{
    public function testEffectiveBehaviorIsDeferredWhenNoWordIsWritten(): void
    {
        self::assertSame(TransactionBehavior::Deferred, (new Begin())->effectiveBehavior());
    }

    public function testEffectiveBehaviorIsTheWrittenWord(): void
    {
        self::assertSame(TransactionBehavior::Exclusive, (new Begin(TransactionBehavior::Exclusive))->effectiveBehavior());
    }

    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('BEGIN IMMEDIATE', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->declarations());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheBehaviorWordAndTheIgnoredName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('BEGIN', $semantics->analyze('begin transaction')->toString());
        self::assertSame('BEGIN DEFERRED', $semantics->analyze('begin deferred')->toString());
        self::assertSame('BEGIN IMMEDIATE TRANSACTION `my tx`', $semantics->analyze('BEGIN IMMEDIATE TRANSACTION "my tx"')->toString());
    }

    public function testRenderWritesANewlyBuiltStart(): void
    {
        $operation = new Operation((new Semantics(Dialect::Sqlite))->context(), new Begin(TransactionBehavior::Exclusive, new Name('t1')));

        self::assertSame('BEGIN EXCLUSIVE TRANSACTION t1', $operation->toString());
    }
}
