<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Column\SerialColumn;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;
use SqlSemantics\Type\Nullability;

#[CoversClass(SerialColumn::class)]
#[Medium]
final class SerialColumnTest extends TestCase
{
    public function testExpressionsAreEmptyBecauseTheSequenceSuppliesTheDefault(): void
    {
        self::assertSame([], (new SerialColumn())->expressions());
    }

    #[TestWith(['smallserial', 'smallint', 'smallserial'])]
    #[TestWith(['serial2', 'smallint', 'smallserial'])]
    #[TestWith(['serial', 'integer', 'serial'])]
    #[TestWith(['serial4', 'integer', 'serial'])]
    #[TestWith(['bigserial', 'bigint', 'bigserial'])]
    #[TestWith(['serial8', 'bigint', 'bigserial'])]
    public function testBindsEachPostgreSqlSerialNameToItsIntegerType(string $declared, string $type, string $written): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build());
        $statement = $binder->bind('CREATE TABLE t (id ' . $declared . ')');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $column = $statement->definition->table->columns[0];
        self::assertInstanceOf(SerialColumn::class, $column->generation);
        self::assertSame($type, $column->type->name);
        self::assertSame(Nullability::NotNull, $column->nullability);
        $expected = 'CREATE TABLE "public"."t"("id" ' . $written . ')';
        self::assertSame($expected, (new SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testDropDefaultLeavesAnOrdinaryColumn(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build('CREATE TABLE t (id serial)', 'ALTER TABLE t ALTER COLUMN id DROP DEFAULT')->tables[0]->columns[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $column->generation);
        self::assertSame('integer', $column->type->name);
        self::assertSame(Nullability::NotNull, $column->nullability);
    }
}
