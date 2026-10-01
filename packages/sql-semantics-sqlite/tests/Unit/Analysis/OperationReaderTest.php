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
use SqlSemantics\Platform\Sqlite\Analysis\OperationReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\InsertRows;
use SqlSemantics\Statement\Inspection\ExplainPlan;
use SqlSemantics\Statement\Inspection\ExplainProgram;
use SqlSemantics\Statement\Maintenance\Analyze;
use SqlSemantics\Statement\Maintenance\Reindex;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\DropTable;
use SqlSemantics\Statement\Schema\RenameColumn;
use SqlSemantics\Statement\Schema\RenameTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\Begin;

#[CoversClass(OperationReader::class)]
#[Medium]
final class OperationReaderTest extends TestCase
{
    #[TestWith(['REINDEX', Reindex::class])]
    #[TestWith(['ANALYZE main.foo', Analyze::class])]
    #[TestWith(['BEGIN EXCLUSIVE', Begin::class])]
    #[TestWith(['DROP TABLE bar', DropTable::class])]
    #[TestWith(['ALTER TABLE bar RENAME TO baz', RenameTable::class])]
    #[TestWith(['ALTER TABLE bar RENAME COLUMN foo TO baz', RenameColumn::class])]
    #[TestWith(['SELECT foo FROM bar', Select::class])]
    #[TestWith(['EXPLAIN SELECT foo FROM bar', ExplainProgram::class])]
    #[TestWith(['EXPLAIN QUERY PLAN SELECT foo FROM bar', ExplainPlan::class])]
    #[TestWith(['REINDEX; ANALYZE;', Sequence::class])]
    #[TestWith([';;;', Sequence::class])]
    #[TestWith(['; REINDEX;;', Reindex::class])]
    public function testReadReturnsTheMeaningOfTheWholeInput(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $operation = $reader->read($parser->parse($sql), $catalog);
        self::assertSame($class, $operation::class);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($reader->read($parser->parse($operation->toString()), $catalog)));
    }

    public function testStatementsNeverApplyEarlierSchemaOperations(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $parser = new SqliteParser();
        $operations = (new OperationReader())->statements($parser->parse('ALTER TABLE bar RENAME COLUMN foo TO baz; DROP TABLE bar; SELECT foo FROM bar;'), $catalog);
        self::assertCount(3, $operations);
        self::assertInstanceOf(RenameColumn::class, $operations[0]);
        self::assertInstanceOf(DropTable::class, $operations[1]);
        self::assertInstanceOf(Select::class, $operations[2]);
        self::assertSame($catalog, $operations[0]->table->catalog);
        self::assertSame($catalog, $operations[1]->table->catalog);
        self::assertSame($catalog, $operations[2]->scope->catalog);
        $expression = $operations[2]->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $expression);
        self::assertInstanceOf(ResolvedColumn::class, $expression->resolution);
        self::assertSame($table, $expression->resolution->table);
        self::assertSame($column, $expression->resolution->column);
    }

    public function testCommandRetainsTableAndColumnIdentityInsideInspection(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $parser = new SqliteParser();
        $query = (new OperationReader())->command($parser->parse('SELECT foo FROM bar')->find('cmd')[0], $catalog);
        self::assertInstanceOf(Select::class, $query);
        $explain = new ExplainPlan($query);
        self::assertSame($query, $explain->operation);
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $expression);
        self::assertInstanceOf(ResolvedColumn::class, $expression->resolution);
        self::assertSame($column, $expression->resolution->column);
    }

    #[TestWith(['EXPLAIN SELECT foo FROM bar WHERE foo > 1'])]
    #[TestWith(['EXPLAIN QUERY PLAN SELECT foo FROM bar WHERE foo > 1'])]
    public function testReadPreservesTheRequestedInspectionResults(string $sql): void
    {
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE bar (foo INTEGER)');
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $operation = (new OperationReader())->read((new SqliteParser())->parse($sql), $catalog);
        $original = $database->query($sql);
        $rebuilt = $database->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
    }

    #[TestWith(['CREATE TABLE bar (foo TEXT)', 'rowid', 'rowid'])]
    #[TestWith(['CREATE TABLE bar (foo TEXT)', '_rowid_', 'rowid'])]
    #[TestWith(['CREATE TABLE bar (Id INTEGER PRIMARY KEY, foo TEXT)', 'oid', 'Id'])]
    #[TestWith(['CREATE TABLE bar (Id INTEGER PRIMARY KEY DESC, foo TEXT)', 'oid', 'rowid'])]
    #[TestWith(['CREATE TABLE bar (Id "INTEGER"(123) PRIMARY KEY, foo TEXT)', 'oid', 'rowid'])]
    #[TestWith(['CREATE TABLE bar (rowid TEXT, foo TEXT)', '_ROWID_', 'rowid'])]
    public function testReadSharesThePhysicalRowIdentityAndMatchesDatabaseOutputNames(string $schema, string $referenceName, string $resultName): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = $reader->read($parser->parse($schema), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, $create->table);
        $query = $reader->read($parser->parse('SELECT ' . $referenceName . ' FROM bar'), $catalog);
        self::assertInstanceOf(Select::class, $query);
        $field = $query->field($resultName);
        self::assertInstanceOf(ColumnReference::class, $field->expression);
        self::assertInstanceOf(ResolvedColumn::class, $field->expression->resolution);
        self::assertNotNull($create->table->rowIdentifier);
        self::assertSame($create->table->rowIdentifier->column, $field->expression->resolution->column);
        self::assertSame(Builtin::Integer, $field->expression->resolution->column->type->name);
        $database = new PDO('sqlite::memory:');
        $database->exec($schema);
        $database->exec('INSERT INTO bar DEFAULT VALUES');
        $result = $database->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([[$resultName => 1]], $result->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testReadKeepsImplicitInsertionColumnsSeparateFromTheRowIdentifier(): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = $reader->read($parser->parse('CREATE TABLE bar (foo TEXT)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $implicit = $reader->read($parser->parse("INSERT INTO bar VALUES ('text')"), $catalog);
        $explicit = $reader->read($parser->parse('INSERT INTO bar (rowid) VALUES (42)'), $catalog);
        self::assertInstanceOf(InsertRows::class, $implicit);
        self::assertInstanceOf(InsertRows::class, $explicit);
        self::assertNotNull($implicit->target->columns);
        self::assertCount(1, $implicit->target->columns);
        self::assertInstanceOf(ResolvedColumn::class, $implicit->target->columns[0]->resolution);
        self::assertSame($create->table->columns[0], $implicit->target->columns[0]->resolution->column);
        self::assertNotNull($explicit->target->columns);
        self::assertInstanceOf(ResolvedColumn::class, $explicit->target->columns[0]->resolution);
        self::assertSame($create->table->rowIdentifier?->column, $explicit->target->columns[0]->resolution->column);
    }
}
