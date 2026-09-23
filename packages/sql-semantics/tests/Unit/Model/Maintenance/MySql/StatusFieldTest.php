<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\StatusColumn;
use SqlSemantics\Model\Maintenance\MySql\StatusField;
use SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatusField::class)]
#[Medium]
final class StatusFieldTest extends TestCase
{
    #[TestWith([0, StatusField::Table])]
    #[TestWith([1, StatusField::Operation])]
    #[TestWith([2, StatusField::MessageType])]
    #[TestWith([3, StatusField::Message])]
    public function testIdentifiesTheResultPosition(int $ordinal, StatusField $field): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECK TABLE t');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $column = $statement->resultColumns()[$ordinal];
        self::assertSame($field->value, $column->name);
        self::assertInstanceOf(StatusColumn::class, $column->expression);
        self::assertSame($field, $column->expression->field);
    }
}
