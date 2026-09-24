<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\CopyTable;
use SqlSemantics\Model\Statement\Loading\Copy\CopyToStatement;
use SqlSemantics\Model\Statement\Loading\Copy\ListedColumns;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyTable::class)]
#[Medium]
final class CopyTableTest extends TestCase
{
    public function testValidateAcceptsForcedCopiedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('COPY t (a, b) TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        CopyTable::validate($statement->origin, $statement->table, ['a', 'b'], new CopyOptions(CopyFormat::Csv, forceQuote: new ListedColumns(['b'])));
        self::assertSame(['a', 'b'], $statement->columns);
    }

    public function testValidateRejectsRepeatedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        CopyTable::validate($statement->origin, $statement->table, ['a', 'a'], new CopyOptions());
    }

    public function testValidateRejectsAForcedColumnOutsideTheList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('COPY t TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        CopyTable::validate($statement->origin, $statement->table, ['a'], new CopyOptions(CopyFormat::Csv, forceQuote: new ListedColumns(['b'])));
    }

    public function testValidateRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        CopyTable::validate(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), $statement->table, [], new CopyOptions());
    }
}
