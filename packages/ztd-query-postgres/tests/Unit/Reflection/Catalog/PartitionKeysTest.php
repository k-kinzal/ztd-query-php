<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\PartitionKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
final class PartitionKeysTest extends TestCase
{
    public function testReflectIgnoresMalformedRowsAndKeepsThePartitionExpression(): void
    {
        $connection = new \Tests\Fake\FakeConnection(defaultRows: [
            ['table_name' => 'events', 'partition_key' => 'RANGE (created_at)'],
            ['table_name' => '', 'partition_key' => 'HASH (id)'],
            ['table_name' => 'invalid', 'partition_key' => null],
        ]);
        $keys = (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\PartitionKeys($connection))->reflect();
        self::assertSame(['events'], array_keys($keys));
        self::assertSame(\ZtdQuery\Schema\TablePartitionStrategy::Range, $keys['events']->strategy);
        self::assertSame(['created_at'], $keys['events']->expressions);
        self::assertSame([], (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\PartitionKeys(new \Tests\Fake\FakeSequentialConnection([])))->reflect());
    }
}
