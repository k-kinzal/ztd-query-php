<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\TableOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Validation\InputViolation;
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
}
