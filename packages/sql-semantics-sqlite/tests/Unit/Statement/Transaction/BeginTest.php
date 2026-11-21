<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;

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
        $operation = (new Semantics())->analyze('BEGIN IMMEDIATE', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->declarations());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheBehaviorWordAndTheIgnoredName(): void
    {
        $semantics = new Semantics();

        self::assertSame('BEGIN', $semantics->analyze('begin transaction')->toString());
        self::assertSame('BEGIN DEFERRED', $semantics->analyze('begin deferred')->toString());
        self::assertSame('BEGIN IMMEDIATE TRANSACTION `my tx`', $semantics->analyze('BEGIN IMMEDIATE TRANSACTION "my tx"')->toString());
    }

    public function testRenderWritesANewlyBuiltStart(): void
    {
        $operation = new Operation((new Semantics())->context(), new Begin(TransactionBehavior::Exclusive, new Name('t1')));

        self::assertSame('BEGIN EXCLUSIVE TRANSACTION t1', $operation->toString());
    }
}
