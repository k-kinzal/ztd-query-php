<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\DistinctOn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DistinctOn::class)]
#[Medium]
final class DistinctOnTest extends TestCase
{
    public function testRetainsTheOrderedKeyExpressions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind('SELECT DISTINCT ON (n, id) id FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DistinctOn::class, $statement->quantifier);
        self::assertSame(['n', 'id'], array_map(static fn (\SqlSemantics\Model\Expression $key): ?string => $key->columnBinding()?->column->name, $statement->quantifier->keys));
        self::assertSame('SELECT DISTINCT ON("n", "id") "id" AS "id" FROM "public"."t"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRequiresAtLeastOneKey(): void
    {
        $this->expectException(InvalidStructure::class);
        new DistinctOn([]);
    }
}
