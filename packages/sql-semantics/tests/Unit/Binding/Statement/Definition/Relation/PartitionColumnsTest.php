<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\PartitionColumns;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionColumns::class)]
#[Medium]
final class PartitionColumnsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (id WITH OPTIONS NOT NULL, n DEFAULT 5 CHECK (n > 0)) DEFAULT SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE "f" PARTITION OF "t"("id" WITH OPTIONS NOT NULL, "n" WITH OPTIONS DEFAULT 5 CHECK (("n" > 0))) DEFAULT SERVER "s"'])]
    public function testReadReadsOverridesWithoutTypes(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (id GENERATED ALWAYS AS IDENTITY, n GENERATED ALWAYS AS (id * 2) STORED) DEFAULT SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE "f" PARTITION OF "t"("id" WITH OPTIONS NOT NULL GENERATED ALWAYS AS IDENTITY, "n" WITH OPTIONS GENERATED ALWAYS AS(("id" * 2)) STORED) DEFAULT SERVER "s"'])]
    public function testGenerationReadsDefaultsIdentityAndExpressions(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (id CONSTRAINT nn NOT NULL) DEFAULT SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE "f" PARTITION OF "t"("id" WITH OPTIONS NOT NULL) DEFAULT SERVER "s"'])]
    public function testWordsIgnoresTheConstraintName(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }
}
