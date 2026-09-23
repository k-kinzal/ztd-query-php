<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement;
use SqlSemantics\Model\Transaction\Xa\RecoveryColumn;
use SqlSemantics\Model\Transaction\Xa\RecoveryEncoding;
use SqlSemantics\Model\Transaction\Xa\RecoveryField;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XaRecoverStatement::class)]
#[Medium]
final class XaRecoverStatementTest extends TestCase
{
    public function testResultColumnsExposeTheFourProducedFieldsWithoutReadingRows(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('XA RECOVER');
        self::assertInstanceOf(XaRecoverStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['formatID', 'gtrid_length', 'bqual_length', 'data'], array_column($columns, 'name'));
        self::assertSame([0, 1, 2, 3], array_column($columns, 'ordinal'));
        self::assertSame('bigint', $columns[0]->expression->type->name);
        self::assertSame('varchar', $columns[3]->expression->type->name);
        self::assertInstanceOf(RecoveryColumn::class, $columns[3]->expression);
        self::assertSame(RecoveryField::Data, $columns[3]->expression->field);
        self::assertSame($statement->scopeId, $columns[3]->expression->scopeId);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $columns[3]->expression->nullability);
    }

    public function testWithEncodingUpdatesTheProducedDataRepresentation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('XA /* layout */ RECOVER');
        self::assertInstanceOf(XaRecoverStatement::class, $statement);
        $changed = $statement->withEncoding(RecoveryEncoding::Hexadecimal);
        self::assertSame('XA RECOVER CONVERT XID', $changed->toString());
        self::assertSame(RecoveryEncoding::Bytes, $statement->encoding);
        $data = $changed->resultColumns()[3]->expression;
        self::assertInstanceOf(RecoveryColumn::class, $data);
        self::assertSame(RecoveryEncoding::Hexadecimal, $data->encoding);
    }

    public function testWithOriginRetainsTheResultRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('XA RECOVER CONVERT XID');
        self::assertInstanceOf(XaRecoverStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->encoding, $copy->encoding);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::Sqlite))->parse('SELECT 1');
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new XaRecoverStatement(new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::Sqlite));
    }
}
