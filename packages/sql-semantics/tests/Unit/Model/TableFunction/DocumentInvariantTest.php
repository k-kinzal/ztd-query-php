<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableFunction\DocumentInvariant;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DocumentInvariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DocumentInvariantTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testCheckAcceptsAConsistentJsonTable(Dialect $dialect): void
    {
        $table = new JsonTable(new Input(Expression::literal('{}', $dialect)), Expression::literal('$', $dialect), [new Ordinality('n')]);
        self::assertSame($dialect, $table->dialect);
    }

    public function testCheckRejectsAJsonTableInSqlite(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::Sqlite)), Expression::literal('$', Dialect::Sqlite), [new Ordinality('n')]);
    }

    public function testCheckRejectsAnXmlTableOutsidePostgreSql(): void
    {
        $this->expectException(InvalidStructure::class);
        new XmlTable(Expression::literal('<r/>', Dialect::MySql), Expression::literal('/r', Dialect::MySql), [new \SqlSemantics\Model\TableFunction\Xml\Ordinality('n')]);
    }

    public function testCheckRejectsAPathFromAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::PostgreSql)), Expression::literal('$', Dialect::MySql), [new Ordinality('n')]);
    }

    public function testCheckRejectsAColumnTypeFromAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::PostgreSql)), Expression::literal('$', Dialect::PostgreSql), [new ValueColumn('v', TypeDescriptor::builtin(Dialect::MySql, 'integer'))]);
    }

    public function testMysqlRequiresALiteralRowPath(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::MySql)), Expression::literal(1, Dialect::MySql), [new Ordinality('n')]);
    }

    public function testMysqlRequiresAnExplicitPathOnValueColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::MySql)), Expression::literal('$', Dialect::MySql), [new ValueColumn('v', TypeDescriptor::builtin(Dialect::MySql, 'integer'))]);
    }

    public function testMysqlRejectsWrapperOptions(): void
    {
        $column = new ValueColumn('v', TypeDescriptor::builtin(Dialect::MySql, 'integer'), Expression::literal('$.v', Dialect::MySql), null, null, ArrayWrapping::Without);
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::MySql)), Expression::literal('$', Dialect::MySql), [$column]);
    }

    public function testTextLiteralAcceptsAPlainOrCharsetIntroducedTextLiteral(): void
    {
        $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT _utf8mb4 '$', '$', X'24', _binary X'24', 1");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame([true, true, false, false, false], array_map(static fn ($output): bool => DocumentInvariant::textLiteral($output->expression), $query->outputs));
    }

    #[TestWith(["DELETE x FROM JSON_TABLE(1, _utf8mb4 'text' COLUMNS (x FOR ORDINALITY)) AS n", "DELETE `x` FROM JSON_TABLE(1, _utf8mb4 'text' COLUMNS(`x` FOR ORDINALITY)) AS `n`"])]
    #[TestWith(["SELECT n.x FROM JSON_TABLE('[1]', _latin1 '$[*]' COLUMNS (x FOR ORDINALITY)) AS n", "SELECT `n`.`x` AS `x` FROM JSON_TABLE('[1]', _latin1 '$[*]' COLUMNS(`x` FOR ORDINALITY)) AS `n`"])]
    public function testMysqlAcceptsACharsetIntroducedRowPath(string $sql, string $written): void
    {
        $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame($sql, $statement->toString());
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
