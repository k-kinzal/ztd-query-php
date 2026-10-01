<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(SemanticGraph::class)]
#[UsesClass(Name::class)]
#[UsesClass(Savepoint::class)]
#[UsesClass(RollbackToSavepoint::class)]
#[UsesClass(TransactionName::class)]
#[Medium]
final class SemanticGraphTest extends TestCase
{
    public function testIsSemanticOperationRejectsAGrammarTreeEvenWhenItReconstructsSql(): void
    {
        $syntax = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1');
        self::assertSame('SELECT 1', $syntax->toString());
        self::assertFalse((new SemanticGraph())->isSemanticOperation($syntax));
    }

    public function testContainsOnlyValuesRejectsSyntaxInsideAnOtherwiseImmutableValue(): void
    {
        $declaration = (new Semantics(Dialect::Sqlite))->type('INTEGER');
        self::assertFalse((new SemanticGraph())->containsOnlyValues($declaration));
        self::assertTrue((new SemanticGraph())->containsOnlyValues($declaration->type));
    }

    public function testFingerprintPreservesSharedValueIdentity(): void
    {
        $name = new Name('mark');
        $shared = new RollbackToSavepoint($name, new TransactionName($name, true));
        $distinct = new RollbackToSavepoint($name, new TransactionName(new Name('mark'), true));
        self::assertSame($shared->toString(), $distinct->toString());
        self::assertNotSame((new SemanticGraph())->fingerprint($shared), (new SemanticGraph())->fingerprint($distinct));
        self::assertTrue((new SemanticGraph())->isSemanticOperation(new Savepoint($name)));
    }
}
