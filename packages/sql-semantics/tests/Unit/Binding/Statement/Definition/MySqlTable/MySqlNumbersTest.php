<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\MySqlNumbers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

#[CoversClass(MySqlNumbers::class)]
#[Medium]
final class MySqlNumbersTest extends TestCase
{
    #[TestWith(['00042', 42])]
    #[TestWith(['0x1f', 31])]
    #[TestWith(['1.9', 1])]
    #[TestWith(['2e5', 2])]
    public function testReadConvertsTheSpelling(string $text, int $expected): void
    {
        $token = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t CHECKSUM = ' . $text)->find('create_table_option')[0];
        self::assertSame($expected, MySqlNumbers::read($token, InputViolation::TableOption));
    }

    public function testReadRejectsADecimalWhereIntegersAreRequired(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t AUTO_INCREMENT = 1.5')->find('create_table_option')[0];
        $this->expectException(InvalidSql::class);
        MySqlNumbers::read($node, InputViolation::PartitionDefinition, true);
    }

    public function testBoundedRejectsANumberBeyondTheSignedRange(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t AUTO_INCREMENT = 18446744073709551615')->find('create_table_option')[0];
        $this->expectException(InvalidSql::class);
        MySqlNumbers::bounded('18446744073709551615', false, $node, InputViolation::TableOption);
    }
}
