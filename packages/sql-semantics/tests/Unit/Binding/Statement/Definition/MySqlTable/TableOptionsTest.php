<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\TableOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Table\MergeInsertMethod;
use SqlSemantics\Schema\Table\RowFormat;
use SqlSemantics\Schema\Table\TableStorage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableOptions::class)]
#[Medium]
final class TableOptionsTest extends TestCase
{
    public function testReadKeepsTheLastValueOfEachOption(): void
    {
        $options = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ENGINE = a ENGINE = b PACK_KEYS = 1 PACK_KEYS = DEFAULT')->find('create_table_options_space_separated')[0];
        [$properties, $resets] = TableOptions::read($options->find('create_table_option'), new Identifiers(Dialect::MySql));
        self::assertSame('b', $properties->engine);
        self::assertNull($properties->packKeys);
        self::assertSame([TableOptionReset::PackKeys], $resets);
    }

    public function testPropertiesDecodesQuotedStrings(): void
    {
        $option = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t COMMENT = \'it\'\'s\'')->find('create_table_option')[0];
        self::assertSame("it's", TableOptions::properties(['comment' => $option->tokens()[2]], new Identifiers(Dialect::MySql), false, null)->comment);
    }

    public function testTextDecodesIdentifiersAndStrings(): void
    {
        $tokens = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ENGINE = `x``y`')->find('create_table_option')[0]->tokens();
        self::assertSame('x`y', TableOptions::text($tokens[2], new Identifiers(Dialect::MySql)));
    }

    public function testSwitchRejectsTwo(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t STATS_PERSISTENT = 2');
    }

    public function testPagesRejectsZero(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t STATS_SAMPLE_PAGES = 0');
    }

    public function testSizeReadsSuffixes(): void
    {
        $tokens = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t AUTOEXTEND_SIZE = 64M')->find('create_table_option')[0]->tokens();
        self::assertSame(67108864, TableOptions::size($tokens[2]));
    }

    public function testUnionReadsQualifiedTables(): void
    {
        $option = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t UNION = (a, db.b)')->find('create_table_option')[0];
        self::assertSame([['a'], ['db', 'b']], array_map(static fn ($table): array => $table->parts, TableOptions::union($option, new Identifiers(Dialect::MySql))));
    }

    public function testReadReadsLowerCaseKeywordsAndValues(): void
    {
        $options = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t engine innodb default charset = utf8mb4 row_format = DYNAMIC storage disk insert_method = last pack_keys = default stats_persistent = 1')->find('create_table_options_space_separated')[0];
        [$properties, $resets] = TableOptions::read($options->find('create_table_option'), new Identifiers(Dialect::MySql));
        self::assertFalse($properties->temporary);
        self::assertSame('innodb', $properties->engine);
        self::assertSame('utf8mb4', $properties->characterSet);
        self::assertSame(RowFormat::Dynamic, $properties->rowFormat);
        self::assertSame(TableStorage::Disk, $properties->storage);
        self::assertSame(MergeInsertMethod::Last, $properties->insertMethod);
        self::assertTrue($properties->statsPersistent);
        self::assertSame([TableOptionReset::PackKeys], $resets);
    }

    public function testReadKeepsReadingAfterAReset(): void
    {
        $options = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t PACK_KEYS = DEFAULT ENGINE = x SECONDARY_ENGINE = NULL')->find('create_table_options_space_separated')[0];
        [$properties, $resets] = TableOptions::read($options->find('create_table_option'), new Identifiers(Dialect::MySql), true);
        self::assertTrue($properties->temporary);
        self::assertSame('x', $properties->engine);
        self::assertSame([TableOptionReset::PackKeys, TableOptionReset::SecondaryEngine], $resets);
    }

    public function testTextReadsTheFirstTokenOfANode(): void
    {
        $option = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ENGINE = x')->find('create_table_option')[0];
        self::assertSame('ENGINE', TableOptions::text($option, new Identifiers(Dialect::MySql)));
    }

    #[TestWith(["'a\\tb'", "a\tb"])]
    #[TestWith(["n'x'", "n'x'"])]
    public function testTextDecodesOnlyQuotedStrings(string $text, string $expected): void
    {
        self::assertSame($expected, TableOptions::text(new Token(0, 'TEXT_STRING', $text, 0), new Identifiers(Dialect::MySql)));
    }

    #[TestWith(['0', false])]
    #[TestWith(['1', true])]
    public function testSwitchReadsZeroAndOne(string $text, bool $expected): void
    {
        self::assertSame($expected, TableOptions::switch(new Token(0, 'NUM', $text, 0)));
    }

    public function testSwitchKeepsAnAbsentValue(): void
    {
        self::assertNull(TableOptions::switch(null));
        self::assertNull(TableOptions::pages(null));
    }

    #[TestWith(['1', 1])]
    #[TestWith(['65535', 65535])]
    public function testPagesAcceptsTheSampledPageRange(string $text, int $expected): void
    {
        self::assertSame($expected, TableOptions::pages(new Token(0, 'NUM', $text, 0)));
    }

    #[TestWith(['0'])]
    #[TestWith(['65536'])]
    public function testPagesRejectsCountsOutsideTheRange(string $text): void
    {
        $this->expectException(InvalidSql::class);
        TableOptions::pages(new Token(0, 'NUM', $text, 0));
    }

    #[TestWith(['123', 123])]
    #[TestWith(['0k', 0])]
    #[TestWith(['000K', 0])]
    #[TestWith(['0005k', 5120])]
    #[TestWith(['2m', 2097152])]
    #[TestWith(['1G', 1073741824])]
    #[TestWith(['1t', 1099511627776])]
    #[TestWith(['0000000000000000001k', 1024])]
    #[TestWith(['9007199254740991k', 9223372036854774784])]
    public function testSizeReadsNumbersAndSuffixedSizes(string $text, int $expected): void
    {
        self::assertSame($expected, TableOptions::size(new Token(0, 'IDENT', $text, 0)));
    }

    public function testSizeReadsTheFirstTokenOfANode(): void
    {
        $size = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t AUTOEXTEND_SIZE = 4k')->find('size_number')[0];
        self::assertSame(4096, TableOptions::size($size));
    }

    #[TestWith(['1.5'])]
    #[TestWith(['a5k'])]
    #[TestWith(['5kx'])]
    #[TestWith(['00000000000000000001k'])]
    #[TestWith(['9007199254740992k'])]
    public function testSizeRejectsMalformedAndOverflowingSizes(string $text): void
    {
        $this->expectException(InvalidSql::class);
        TableOptions::size(new Token(0, 'IDENT', $text, 0));
    }
}
