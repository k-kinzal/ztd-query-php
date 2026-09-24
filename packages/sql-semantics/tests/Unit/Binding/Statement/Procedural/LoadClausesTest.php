<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\LoadClauses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(LoadClauses::class)]
#[Medium]
final class LoadClausesTest extends TestCase
{
    public function testLayoutKeepsTheLastSeparatorAndAStickyOptionally(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ROWS IDENTIFIED BY 'r' FIELDS OPTIONALLY ENCLOSED BY '\"' TERMINATED BY ';' ENCLOSED BY 0x27 TERMINATED BY ',' LINES TERMINATED BY '|' STARTING BY '>' IGNORE 3 ROWS");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        $layout = $statement->layout;
        self::assertSame(["','", '0x27', true, "'|'", "'>'", 3], [$layout->fields->terminator?->text, $layout->fields->enclosure?->text, $layout->fields->optionallyEnclosed, $layout->lines->terminator?->text, $layout->lines->start?->text, $layout->skippedRows]);
    }

    #[TestWith(['mysql-5.6.51', 'CHARSET DEFAULT', null])]
    #[TestWith(['mysql-5.7.44', "CHARACTER SET 'UTF8'", 'UTF8'])]
    #[TestWith(['mysql-8.4.7', 'CHARACTER SET BINARY', 'binary'])]
    #[TestWith(['mysql-8.4.7', 'CHARACTER SET `BINARY`', 'BINARY'])]
    public function testCharacterSetReadsTheNameOrDefault(string $version, string $clause, ?string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t " . $clause);
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame($name, $statement->layout->characterSet);
    }

    public function testLiteralBindsTheSeparatorEndingAClause(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA INFILE 'f' INTO TABLE t FIELDS ESCAPED BY b'1011100'")->find('field_term')[0];
        self::assertSame("b'1011100'", LoadClauses::literal($node)->text);
    }

    public function testTokenRejectsANonLiteralToken(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA INFILE 'f' INTO TABLE t")->find('table_ident')[0];
        $this->expectException(InvalidSql::class);
        LoadClauses::token($node->tokens()[0], $node);
    }

    #[TestWith(["LOAD DATA INFILE 'f' FILES 2 INTO TABLE t ALGORITHM = BULK"])]
    #[TestWith(["LOAD DATA INFILE 'f' COUNT 0 INTO TABLE t ALGORITHM = BULK"])]
    public function testFileCountRejectsAnotherWordOrZeroFiles(string $sql): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('load_stmt')[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::LoadOption->message());
        LoadClauses::fileCount($node);
    }

    public function testNumberReadsTheClauseValue(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA INFILE 'f' INTO TABLE t PARALLEL = 16 ALGORITHM = BULK")->find('load_stmt')[0];
        self::assertSame([16, null], [LoadClauses::number($node, 'opt_load_parallel'), LoadClauses::number($node, 'opt_load_memory')]);
    }

    #[TestWith(['00', '0'])]
    #[TestWith(['3G', '3221225472'])]
    #[TestWith(['0xFFFFFFFFFFFFFFFF', '18446744073709551615'])]
    public function testMemoryReadsDecimalBytes(string $size, string $bytes): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA INFILE 'f' INTO TABLE t MEMORY = " . $size . ' ALGORITHM = BULK')->find('load_stmt')[0];
        self::assertSame($bytes, LoadClauses::memory($node));
    }

    #[TestWith(['10X'])]
    #[TestWith(['2147483648K'])]
    public function testMemoryRejectsAWrongSize(string $size): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA INFILE 'f' INTO TABLE t MEMORY = " . $size . ' ALGORITHM = BULK')->find('load_stmt')[0];
        $this->expectException(SemanticException::class);
        LoadClauses::memory($node);
    }

    public function testDecimalConvertsHexadecimalDigits(): void
    {
        self::assertSame(['0', '255', '4096'], [LoadClauses::decimal('0'), LoadClauses::decimal('ff'), LoadClauses::decimal('1000')]);
    }
}
