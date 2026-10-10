<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Result\ColumnFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Heading::class)]
#[Small]
final class HeadingTest extends TestCase
{
    public function testTextCreatesATextColumn(): void
    {
        $heading = Heading::text('Field', Field::VarString, 64, 0, 0, 'Field', 'COLUMNS');

        self::assertTrue($heading->text);
        self::assertSame(['Field', 'COLUMNS', 'utf8mb3_general_ci'], [$heading->originalName, $heading->table, $heading->collation]);
    }

    public function testColumnCountsTheBytesOfTheCharactersInTheCharacterSetOfTheResults(): void
    {
        $heading = Heading::text('Type', Field::Blob, 16777215, ColumnFlag::NotNull->value | ColumnFlag::Blob->value, 0, 'Type', 'COLUMNS', 'columns');

        $utf8 = $heading->column(Charset::known('utf8mb4'));
        $latin1 = $heading->column(Charset::known('latin1'));
        $held = $heading->column(null);

        self::assertSame([67108860, 255], [$utf8->length, $utf8->charset]);
        self::assertSame([16777215, 8], [$latin1->length, $latin1->charset]);
        self::assertSame([50331645, 33], [$held->length, $held->charset]);
    }

    public function testColumnSendsANumberInTheBinaryCharacterSet(): void
    {
        $column = (new Heading('Id', Field::LongLong, 20, ColumnFlag::NotNull->value))->column(Charset::known('utf8mb4'));

        self::assertSame([20, 63, 1], [$column->length, $column->charset, $column->flags]);
    }

    public function testDomainReadsTextAndNumbers(): void
    {
        $text = Heading::text('Field', Field::VarString, 64)->domain();
        $number = (new Heading('Rows', Field::LongLong, 21, ColumnFlag::Unsigned->value))->domain();

        self::assertSame([Kind::String, 'utf8mb3_general_ci', true], [$text->kind, $text->collation->name, $text->nullable]);
        self::assertSame([Kind::Integer, true], [$number->kind, $number->unsigned]);
    }

    public function testDomainRetainsTemporalMeaningWhenTheProtocolUsesACharacterSet(): void
    {
        $heading = Heading::text('Created', Field::Timestamp, 19, ColumnFlag::NotNull->value);

        self::assertSame(76, $heading->column(Charset::known('utf8mb4'))->length);
        self::assertSame(Kind::DateTime, $heading->domain()->kind);
        self::assertSame(Field::Timestamp, $heading->domain()->field);
    }
}
