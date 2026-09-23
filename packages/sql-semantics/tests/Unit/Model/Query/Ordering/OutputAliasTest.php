<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Ordering\OutputAlias;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OutputAlias::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OutputAliasTest extends TestCase
{
    public function testRefersToTheOutputWithoutRepeatingItsComputation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT random() AS draw ORDER BY draw', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(OutputAlias::class, $key);
        self::assertSame($statement->outputs[0], $key->output);
        self::assertSame(1, substr_count(strtoupper($statement->toString()), '"RANDOM"()'));
        self::assertStringEndsWith('ORDER BY "draw" ASC', $statement->toString());
    }

    public function testWindowOrderingUsesItsInputExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT "count"(*) OVER (ORDER BY 1) AS n');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('SELECT "count"(*) OVER (ORDER BY 1 ASC) AS "n"', $statement->toString());
    }
}
