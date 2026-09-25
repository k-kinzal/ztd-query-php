<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\TableAccess;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableAccess::class)]
#[Medium]
final class TableAccessTest extends TestCase
{
    #[TestWith(['mysql', 'SELECT a FROM t PARTITION (p0) TABLESAMPLE BERNOULLI (5)'])]
    #[TestWith(['sqlite', 'SELECT a FROM t INDEXED BY ix'])]
    #[TestWith(['postgresql', 'SELECT a FROM t TABLESAMPLE m (1) REPEATABLE (2)'])]
    public function testCheckAcceptsTheClausesOfTheOwningDialect(string $dialect, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::from($dialect)))->build('CREATE TABLE t(a INTEGER)', 'CREATE INDEX ix ON t(a)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\TableUse::class, $statement->from);
        TableAccess::check($statement->from, Dialect::from($dialect));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql', 'SELECT a FROM t PARTITION (p0)', 'sqlite'])]
    #[TestWith(['sqlite', 'SELECT a FROM t NOT INDEXED', 'mysql'])]
    #[TestWith(['postgresql', 'SELECT a FROM t TABLESAMPLE SYSTEM (1)', 'sqlite'])]
    #[TestWith(['postgresql', 'SELECT a FROM t TABLESAMPLE SYSTEM (1) REPEATABLE (2)', 'mysql'])]
    #[TestWith(['postgresql', 'SELECT a FROM t TABLESAMPLE m (1)', 'mysql'])]
    public function testCheckRejectsAClauseOfAnotherDialect(string $dialect, string $sql, string $other): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::from($dialect)))->build('CREATE TABLE t(a INTEGER)', 'CREATE INDEX ix ON t(a)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\TableUse::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        TableAccess::check($statement->from, Dialect::from($other));
    }
}
