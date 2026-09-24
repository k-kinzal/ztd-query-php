<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\AllRows;
use SqlSemantics\Model\Query\DistinctOn;
use SqlSemantics\Model\Query\DistinctRows;
use SqlSemantics\Model\Query\Quantifier;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Quantifier::class)]
#[Medium]
final class QuantifierTest extends TestCase
{
    #[TestWith(['SELECT id FROM t', AllRows::class])]
    #[TestWith(['SELECT DISTINCT id FROM t', DistinctRows::class])]
    #[TestWith(['SELECT DISTINCT ON (id) id FROM t', DistinctOn::class])]
    public function testClassifiesEachDuplicateEliminationForm(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($class, $statement->quantifier::class);
    }
}
