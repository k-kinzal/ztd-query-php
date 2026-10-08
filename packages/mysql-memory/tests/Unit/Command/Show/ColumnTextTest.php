<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ColumnText;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ColumnText::class)]
#[Small]
final class ColumnTextTest extends TestCase
{
    public function testWrittenAnswersTheDeclaredType(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT ZEROFILL)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $written = (new ColumnText())->written($table->definition, $table->definition->columns[0]);

        self::assertInstanceOf(Integral::class, $written);
        self::assertSame([NumericModifier::Zerofill], $written->modifiers);
    }

    public function testElementAnswersTheColumnDefinition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, B INT)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('B', (new ColumnText())->element($table->definition, $table->definition->columns[1])?->name->column->value);
    }

    public function testExplicitTellsWhetherTheColumnNamesItsCharacterSetOrCollation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a VARCHAR(3) CHARACTER SET utf8mb4, b VARCHAR(3) COLLATE utf8mb4_bin, c VARCHAR(3))');
        $table = $session->instance->dictionary->table('d', 't');
        $text = new ColumnText();

        self::assertNotNull($table);
        self::assertSame([true, true, false], array_map(static fn (ColumnDefinition $column): bool => $text->explicit($text->element($table->definition, $column)), $table->definition->columns));
    }

    public function testTypeWritesTypesAsTheServerLists(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a TINYINT(1), b TINYINT(1) UNSIGNED, c MEDIUMINT(5), d SMALLINT ZEROFILL, e FLOAT(7,3), f DECIMAL, g CHAR(5), h VARBINARY(9), i MEDIUMTEXT, j ENUM('a','b''c'), k DATETIME(3), l POINT, m BOOL)");
        $table = $session->instance->dictionary->table('d', 't');
        $text = new ColumnText();

        self::assertNotNull($table);
        self::assertSame(
            ['tinyint(1)', 'tinyint unsigned', 'mediumint', 'smallint(5) unsigned zerofill', 'float(7,3)', 'decimal(10,0)', 'char(5)', 'varbinary(9)', 'mediumtext', "enum('a','b''c')", 'datetime(3)', 'point', 'tinyint(1)'],
            array_map(static fn (ColumnDefinition $column): string => $text->type($column->domain, $text->written($table->definition, $column)), $table->definition->columns),
        );
    }

    public function testZeroFillTellsWhetherANumericTypeIsZerofill(): void
    {
        self::assertSame([true, false, false], [
            (new ColumnText())->zeroFill(new Integral(IntegralKind::Int, null, [NumericModifier::Zerofill])),
            (new ColumnText())->zeroFill(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])),
            (new ColumnText())->zeroFill(null),
        ]);
    }

    public function testUnsignedWritesTheSignAttributes(): void
    {
        self::assertSame([' unsigned zerofill', ' unsigned', '', ''], [
            (new ColumnText())->unsigned(new Domain(Kind::Integer, Field::Long, 10), new Integral(IntegralKind::Int, null, [NumericModifier::Zerofill])),
            (new ColumnText())->unsigned(new Domain(Kind::Integer, Field::Long, 10, 0, true), null),
            (new ColumnText())->unsigned(new Domain(Kind::Year, Field::Year, 4, 0, true), null),
            (new ColumnText())->unsigned(new Domain(Kind::Integer, Field::Long, 11), null),
        ]);
    }

    public function testWidthWritesTheDisplayWidthOfAnIntegerType(): void
    {
        self::assertSame(['(10)', '(1)', '', ''], [
            (new ColumnText())->width(new Domain(Kind::Integer, Field::Long, 10), new Integral(IntegralKind::Int, null, [NumericModifier::Zerofill])),
            (new ColumnText())->width(new Domain(Kind::Integer, Field::Tiny, 4, display: 1), null),
            (new ColumnText())->width(new Domain(Kind::Integer, Field::Tiny, 3, 0, true, display: 1), null),
            (new ColumnText())->width(new Domain(Kind::Integer, Field::Long, 11), null),
        ]);
    }

    public function testBlobNamesTheSizeFromTheLength(): void
    {
        $binary = Collation::binary();
        $text = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(['tinyblob', 'text', 'mediumtext', 'longblob'], [
            (new ColumnText())->blob(Domain::string(255, $binary, Field::Blob)),
            (new ColumnText())->blob(Domain::string(65535, $text, Field::Blob)),
            (new ColumnText())->blob(Domain::string(16777215, $text, Field::Blob)),
            (new ColumnText())->blob(Domain::string(4294967295, $binary, Field::Blob)),
        ]);
    }

    public function testTextualTellsWhetherAColumnHoldsCharacters(): void
    {
        self::assertTrue((new ColumnText())->textual(Domain::string(5, Collation::known('latin1_swedish_ci'))));
        self::assertFalse((new ColumnText())->textual(Domain::string(5, Collation::binary())));
        self::assertFalse((new ColumnText())->textual(Domain::integer()));
    }

    public function testShownDefaultWritesDefaultsAsShowColumnsReports(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 7, b BIT(3) DEFAULT b'101', c BINARY(3) DEFAULT 'ab', d DECIMAL(7,3) DEFAULT 1, e TIMESTAMP DEFAULT CURRENT_TIMESTAMP, f VARCHAR(5) DEFAULT 'it''s', g INT, h INT AUTO_INCREMENT KEY)");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['7', "b'101'", '0x6162', '1.000', 'CURRENT_TIMESTAMP', "it's", null, null], array_map(static fn (ColumnDefinition $column): ?string => (new ColumnText())->shownDefault($column), $table->definition->columns));
    }

    public function testCreateDefaultWritesDefaultClausesAsShowCreateTableWrites(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 7, b BIT(1) DEFAULT 1, c BINARY(3) DEFAULT 'ab', d TEXT, e JSON, f INT NOT NULL, g INT)");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([" DEFAULT '7'", " DEFAULT b'1'", " DEFAULT 'ab\\0'", '', ' DEFAULT NULL', '', ' DEFAULT NULL'], array_map(static fn (ColumnDefinition $column): string => (new ColumnText())->createDefault($column), $table->definition->columns));
    }

    public function testExtraWritesTheExtraAttributes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT AUTO_INCREMENT KEY, b DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), c INT INVISIBLE)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['auto_increment', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP(3)', 'INVISIBLE'], array_map(static fn (ColumnDefinition $column): string => (new ColumnText())->extra($column), $table->definition->columns));
    }

    public function testBitsWritesABitLiteral(): void
    {
        self::assertSame(["b'101'", "b'100000001'", "b'0'"], [(new ColumnText())->bits("\x05"), (new ColumnText())->bits("\x01\x01"), (new ColumnText())->bits(0)]);
    }

    public function testQuotedDoublesQuotesAndEscapesBackslashes(): void
    {
        self::assertSame("'it''s a\\\\b\\0'", (new ColumnText())->quoted("it's a\\b\0"));
    }

    public function testUtf8ConvertsTheTextOfAColumnCharacterSet(): void
    {
        self::assertSame('é', (new ColumnText())->utf8("\xE9", Domain::string(1, Collation::known('latin1_swedish_ci'))));
        self::assertSame("\xE9", (new ColumnText())->utf8("\xE9", Domain::string(1, Collation::binary())));
    }
}
