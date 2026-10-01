<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\InsertionReader;
use SqlSemantics\Platform\Sqlite\Analysis\OperationReader;
use SqlSemantics\Platform\Sqlite\Analysis\QueryReader;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Insertion\Arity;
use SqlSemantics\Statement\Insertion\InsertDefaults;
use SqlSemantics\Statement\Insertion\InsertRows;
use SqlSemantics\Statement\Insertion\InsertSelect;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(InsertionReader::class)]
#[Medium]
final class InsertionReaderTest extends TestCase
{
    #[TestWith(["INSERT INTO bar VALUES (2, 'two')", InsertRows::class])]
    #[TestWith(["INSERT INTO bar (foo) VALUES ('two'), ('three')", InsertRows::class])]
    #[TestWith(["INSERT INTO main.bar AS target (foo) VALUES ('two')", InsertRows::class])]
    #[TestWith(["INSERT OR IGNORE INTO bar (foo) VALUES ('existing'), ('two')", InsertRows::class])]
    #[TestWith(["REPLACE INTO bar (foo) VALUES ('existing')", InsertRows::class])]
    #[TestWith(["INSERT INTO bar (foo) SELECT 'two'", InsertSelect::class])]
    #[TestWith(["INSERT OR REPLACE INTO bar (foo) SELECT 'existing'", InsertSelect::class])]
    #[TestWith(['INSERT OR IGNORE INTO bar (foo) SELECT foo FROM bar', InsertSelect::class])]
    #[TestWith(['INSERT INTO bar DEFAULT VALUES', InsertDefaults::class])]
    public function testReadPreservesInsertionFormsAndActualDatabaseEffects(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $schema = 'CREATE TABLE bar (id INTEGER PRIMARY KEY, foo TEXT UNIQUE)';
        $create = $reader->read($parser->parse($schema), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $operation = $reader->read($parser->parse($sql), $catalog);
        self::assertSame($class, $operation::class);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($reader->read($parser->parse($operation->toString()), $catalog)));
        $original = new PDO('sqlite::memory:');
        $rebuilt = new PDO('sqlite::memory:');
        $original->exec($schema);
        $rebuilt->exec($schema);
        $original->exec("INSERT INTO bar (foo) VALUES ('existing')");
        $rebuilt->exec("INSERT INTO bar (foo) VALUES ('existing')");
        $original->exec($sql);
        $rebuilt->exec($operation->toString());
        $originalRows = $original->query('SELECT id, foo FROM bar ORDER BY id');
        $rebuiltRows = $rebuilt->query('SELECT id, foo FROM bar ORDER BY id');
        self::assertInstanceOf(PDOStatement::class, $originalRows);
        self::assertInstanceOf(PDOStatement::class, $rebuiltRows);
        self::assertSame($originalRows->fetchAll(PDO::FETCH_ASSOC), $rebuiltRows->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRelationDistinguishesDatabaseTableAndAlias(): void
    {
        $parser = new SqliteParser();
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = (new InsertionReader())->relation($parser->parse('INSERT INTO main.bar AS b (foo) VALUES (1)')->find('xfullname')[0], $catalog);
        self::assertSame('bar', $relation->name->name->value);
        self::assertSame('main', $relation->name->schema?->value);
        self::assertSame('b', $relation->alias?->value);
    }

    public function testReadRequiresAStatementLevelChangeBetweenRowsAndQueries(): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = $reader->read($parser->parse('CREATE TABLE bar (foo INTEGER)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $values = (new InsertionReader())->read($parser->parse('INSERT INTO bar (foo) VALUES (1)')->find('cmd')[0], $catalog);
        self::assertInstanceOf(InsertRows::class, $values);
        $query = (new QueryReader())->read($parser->parse('SELECT foo FROM bar')->find('select')[0], $catalog);
        self::assertInstanceOf(Select::class, $query);
        $select = new InsertSelect($values->target, $query);
        self::assertSame($values->target, $select->target);
        self::assertNotSame($values->target->scope, $query->scope);
        self::assertNotNull($select->target->columns);
        self::assertInstanceOf(ResolvedColumn::class, $select->target->columns[0]->resolution);
        self::assertSame($create->columns[0]->column, $select->target->columns[0]->resolution->column);
        self::assertSame(Arity::Matching, $values->arity());
        self::assertSame(Arity::Matching, $select->arity());
        self::assertSame('INSERT INTO bar (foo) VALUES (1)', $values->toString());
        self::assertSame('INSERT INTO bar (foo) SELECT foo FROM bar', $select->toString());
    }

    public function testReadKeepsDestinationColumnsOutOfValueExpressionScope(): void
    {
        $parser = new SqliteParser();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $reader = new OperationReader();
        $create = $reader->read($parser->parse('CREATE TABLE bar (foo INTEGER)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $sql = 'INSERT INTO bar (foo) VALUES (foo)';
        $operation = (new InsertionReader())->read($parser->parse($sql)->find('cmd')[0], $catalog);
        self::assertInstanceOf(InsertRows::class, $operation);
        $reference = $operation->rows->rows[0]->expressions[0];
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertSame(Invalid::MissingColumn, $reference->type());
        self::assertSame([], $reference->scope->tables);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE bar (foo INTEGER)');
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: foo');
        $database->exec($sql);
    }

    #[TestWith(['INSERT INTO bar VALUES (1)', Arity::Mismatch])]
    #[TestWith(['INSERT INTO bar (foo) VALUES (1), (2, 3)', Arity::Mismatch])]
    #[TestWith(['INSERT INTO bar (foo) SELECT 1, 2', Arity::Mismatch])]
    #[TestWith(['INSERT INTO bar (foo) DEFAULT VALUES', Arity::Mismatch])]
    public function testReadRetainsKnownWidthContradictions(string $sql, Arity $expected): void
    {
        $parser = new SqliteParser();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = (new OperationReader())->read($parser->parse('CREATE TABLE bar (id INTEGER, foo INTEGER)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $operation = (new InsertionReader())->read($parser->parse($sql)->find('cmd')[0], $catalog);
        self::assertSame($expected, $operation->arity());
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE bar (id INTEGER, foo INTEGER)');
        $this->expectException(PDOException::class);
        $database->exec($sql);
    }
}
