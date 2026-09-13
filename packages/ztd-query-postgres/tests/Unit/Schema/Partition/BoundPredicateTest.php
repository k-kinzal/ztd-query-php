<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
final class BoundPredicateTest extends TestCase
{
    public function testRangePredicate(): void
    {
        $sql = 'FROM (10) TO (20)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $key = new \ZtdQuery\Schema\TablePartitionKey(\ZtdQuery\Schema\TablePartitionStrategy::Range, ['id']);

        self::assertSame('(id) >= 10 AND (id) < 20', (new \ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate())->rangePredicate($sql, $tokens, 0, $key));
    }

    public function testListPredicate(): void
    {
        $sql = 'IN (1, 2, NULL)';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $key = new \ZtdQuery\Schema\TablePartitionKey(\ZtdQuery\Schema\TablePartitionStrategy::Range, ['id']);

        self::assertSame('((id) IN (1, 2) OR (id) IS NULL)', (new \ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate())->listPredicate($sql, $tokens, 0, $key));
    }

    public function testRangeBoundary(): void
    {
        self::assertSame('(id) >= 10', (new \ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate())->rangeBoundary(['id'], ['10'], '>=', 'MINVALUE'));
    }
}
