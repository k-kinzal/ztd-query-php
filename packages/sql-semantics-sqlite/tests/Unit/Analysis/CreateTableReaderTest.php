<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\CreateTableReader;
use SqlSemantics\Platform\Sqlite\Analysis\OperationReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(CreateTableReader::class)]
#[Medium]
final class CreateTableReaderTest extends TestCase
{
    #[TestWith(['CREATE TABLE bar (foo INTEGER)'])]
    #[TestWith(['CREATE TABLE bar (id INTEGER PRIMARY KEY, foo TEXT NOT NULL)'])]
    #[TestWith(['CREATE TABLE bar (id INTEGER PRIMARY KEY DESC, foo TEXT UNIQUE)'])]
    #[TestWith(['CREATE TEMPORARY TABLE IF NOT EXISTS bar (foo TEXT NOT NULL ON CONFLICT FAIL)'])]
    #[TestWith(['CREATE TABLE main.bar (foo ANY, id INTEGER PRIMARY KEY AUTOINCREMENT) STRICT'])]
    #[TestWith(['CREATE TABLE bar (foo VARCHAR(-2.5, 0xFF), id "INTEGER"(12) PRIMARY KEY)'])]
    #[TestWith(['CREATE TABLE bar (foo A /*INT*/ B, id "INTEGER" PRIMARY KEY)'])]
    #[TestWith(['CREATE TABLE bar (foo INT CONSTRAINT uq UNIQUE ON CONFLICT IGNORE, blank)'])]
    public function testReadPreservesDatabaseDeclarationsAndSemanticGraph(string $sql): void
    {
        $reader = new CreateTableReader();
        $parser = new SqliteParser();
        $create = $reader->read($parser->parse($sql)->find('cmd')[0]);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($create));
        self::assertSame($graph->fingerprint($create), $graph->fingerprint($reader->read($parser->parse($create->toString())->find('cmd')[0])));
        $database = new PDO('sqlite::memory:');
        $rebuiltDatabase = new PDO('sqlite::memory:');
        $database->exec($sql);
        $rebuiltDatabase->exec($create->toString());
        $original = $database->query('PRAGMA table_xinfo(bar)');
        $rebuilt = $rebuiltDatabase->query('PRAGMA table_xinfo(bar)');
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testColumnsKeepDefinitionOrderAndExactColumnIdentities(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE bar (first INTEGER, second TEXT)');
        $columns = (new CreateTableReader())->columns($tree->find('columnlist')[0]);
        self::assertCount(2, $columns);
        self::assertSame('first', $columns[0]->column->name->value);
        self::assertSame('second', $columns[1]->column->name->value);
        self::assertSame(Builtin::Text, $columns[1]->column->type->name);
    }

    public function testReadBindsAnIndependentSelectToTheExactCreateDeclaration(): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = $reader->read($parser->parse('CREATE TABLE bar (foo INTEGER NOT NULL)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, $create->table);
        $query = $reader->read($parser->parse('SELECT foo FROM bar'), $catalog);
        self::assertInstanceOf(Select::class, $query);
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $expression);
        self::assertInstanceOf(ResolvedColumn::class, $expression->resolution);
        self::assertSame($create->table, $expression->resolution->table);
        self::assertSame($create->columns[0]->column, $expression->resolution->column);
        self::assertSame($create->columns[0]->type->descriptor, $expression->type());
    }
}
