<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\IndexDirective;
use SqlSemantics\Model\Query\Optimization\IndexedBy;
use SqlSemantics\Model\Query\Optimization\NotIndexed;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IndexDirective::class)]
#[Medium]
final class IndexDirectiveTest extends TestCase
{
    /**
     * @param class-string<IndexDirective> $class
     */
    #[TestWith(['SELECT a FROM t INDEXED BY ix', IndexedBy::class])]
    #[TestWith(['SELECT a FROM t NOT INDEXED', NotIndexed::class])]
    public function testSelectsANamedIndexOrNoIndex(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER); CREATE INDEX ix ON t(a)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        self::assertInstanceOf($class, $statement->from->indexing);
    }
}
