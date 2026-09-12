<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlRewriter;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

#[CoversNothing]
final class SqlRewriterTest extends TestCase
{
    public function testRewriteAnswersAPlanForTheStatementItWasGiven(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(QueryKind::READ, $rewriter->rewrite('SELECT 1')->kind());
    }

    public function testRewriteAnswersASimulatedWriteForAStatementThatWouldChangeSomething(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            $rewriter->rewrite('INSERT INTO users (id) VALUES (1)')->kind(),
        );
    }

    public function testRewriteMultipleAnswersOnePlanPerStatementOfABatch(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertCount(2, $rewriter->rewriteMultiple('SELECT 1; SELECT 2')->plans());
    }

    public function testSplitStatementsAnswersTheStatementsABatchIsWrittenAs(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(['SELECT 1', 'SELECT 2'], $rewriter->splitStatements('SELECT 1; SELECT 2'));
    }

    public function testEmptyResultSelectAnswersAStatementThatYieldsNoRows(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertNotSame('', $rewriter->emptyResultSelect());
    }

    public function testTransactionStatementIsNothingForAStatementThatIsNotOne(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertNull($rewriter->transactionStatement('SELECT 1'));
    }
}
