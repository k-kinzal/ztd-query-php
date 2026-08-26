<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeTransactionStatementParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;

#[CoversNothing]
final class TransactionStatementParserTest extends TestCase
{
    public function testParseIsNothingForAStatementThatIsNotATransactionOne(): void
    {
        self::assertNull((new FakeTransactionStatementParser())->parse('SELECT 1'));
    }

    public function testParseReadsAStatementThatOpensATransaction(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);
        $transactions = new ShadowTransactions($store, new TableDefinitionRegistry());

        $statement = (new FakeTransactionStatementParser())->parse('BEGIN');

        self::assertNotNull($statement);
        $statement->apply($transactions);
        $store->set('order', []);
        $transactions->rollBack();

        self::assertSame([['id' => 1]], $store->get('order'));
    }

    public function testParseReadsAStatementThatDeclaresASavepoint(): void
    {
        $store = new ShadowStore();
        $transactions = new ShadowTransactions($store, new TableDefinitionRegistry());
        $parser = new FakeTransactionStatementParser();

        $statement = $parser->parse('SAVEPOINT sp1');

        self::assertNotNull($statement);
        $statement->apply($transactions);

        self::assertSame(0, $transactions->positionOf('SP1'));
    }

    public function testParseReadsAStatementThatGivesUpASavepoint(): void
    {
        $store = new ShadowStore();
        $transactions = new ShadowTransactions($store, new TableDefinitionRegistry());
        $parser = new FakeTransactionStatementParser();
        $parser->parse('SAVEPOINT sp1')?->apply($transactions);

        $statement = $parser->parse('RELEASE sp1');

        self::assertNotNull($statement);
        $statement->apply($transactions);

        self::assertNull($transactions->positionOf('SP1'));
    }
}
