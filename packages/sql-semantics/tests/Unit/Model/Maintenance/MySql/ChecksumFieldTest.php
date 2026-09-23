<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\ChecksumColumn;
use SqlSemantics\Model\Maintenance\MySql\ChecksumField;
use SqlSemantics\Model\Statement\Maintenance\MySql\ChecksumTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChecksumField::class)]
#[Medium]
final class ChecksumFieldTest extends TestCase
{
    #[TestWith([0, ChecksumField::Table])]
    #[TestWith([1, ChecksumField::Checksum])]
    public function testIdentifiesTheResultPosition(int $ordinal, ChecksumField $field): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $column = $statement->resultColumns()[$ordinal];
        self::assertSame($field->value, $column->name);
        self::assertInstanceOf(ChecksumColumn::class, $column->expression);
        self::assertSame($field, $column->expression->field);
    }
}
