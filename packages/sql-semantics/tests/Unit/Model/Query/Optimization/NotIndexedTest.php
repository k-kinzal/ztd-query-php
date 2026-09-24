<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Optimization\NotIndexed;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NotIndexed::class)]
#[Medium]
final class NotIndexedTest extends TestCase
{
    public function testDeletionTargetKeepsNotIndexed(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('DELETE FROM t AS x NOT INDEXED WHERE a = 1');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->target);
        self::assertInstanceOf(NotIndexed::class, $statement->target->indexing);
        self::assertSame('DELETE FROM "main"."t" AS "x" NOT INDEXED WHERE ("a" = 1)', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
