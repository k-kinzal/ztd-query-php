<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Ordering\OutputPosition;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OutputPosition::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OutputPositionTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRetainsTheCompoundOutputPosition(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind('SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 2 OFFSET 1');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(OutputPosition::class, $key);
        self::assertSame($statement->outputs[0], $key->output);
        self::assertSame([], $statement->left->orderBy);
        self::assertSame([], $statement->right->orderBy);
        self::assertSame('SELECT 1 UNION SELECT 2 ORDER BY 1 ASC LIMIT 2 OFFSET 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
