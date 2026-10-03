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
use SqlSemantics\Platform\Sqlite\Analysis\MutationReader;
use SqlSemantics\Platform\Sqlite\Analysis\OperationReader;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Mutation\SqliteDelete;
use SqlSemantics\Statement\Mutation\SqliteUpdate;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(MutationReader::class)]
#[Medium]
final class MutationReaderTest extends TestCase
{
    #[TestWith(['DELETE FROM bar', SqliteDelete::class])]
    #[TestWith(['DELETE FROM bar AS target WHERE target.a > 2', SqliteDelete::class])]
    #[TestWith(['DELETE FROM bar WHERE NULL', SqliteDelete::class])]
    #[TestWith(['UPDATE bar SET a = b, b = a', SqliteUpdate::class])]
    #[TestWith(['UPDATE bar SET a = b, a = 9', SqliteUpdate::class])]
    #[TestWith(['UPDATE bar AS target SET a = target.b + 1 WHERE target.a > 2', SqliteUpdate::class])]
    #[TestWith(['UPDATE OR IGNORE bar SET a = NULL', SqliteUpdate::class])]
    #[TestWith(['UPDATE bar SET a = rhs.b FROM bar AS rhs WHERE bar.a = rhs.a', SqliteUpdate::class])]
    public function testReadPreservesDatabaseEffectsWithoutSimulatingThem(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $schema = 'CREATE TABLE bar (a INTEGER NOT NULL, b INTEGER)';
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
        $original->exec('INSERT INTO bar VALUES (1, 2), (3, 4), (5, 6)');
        $rebuilt->exec('INSERT INTO bar VALUES (1, 2), (3, 4), (5, 6)');
        $original->exec($sql);
        $rebuilt->exec($operation->toString());
        $originalRows = $original->query('SELECT a, b FROM bar ORDER BY rowid');
        $rebuiltRows = $rebuilt->query('SELECT a, b FROM bar ORDER BY rowid');
        self::assertInstanceOf(PDOStatement::class, $originalRows);
        self::assertInstanceOf(PDOStatement::class, $rebuiltRows);
        self::assertSame($originalRows->fetchAll(PDO::FETCH_ASSOC), $rebuiltRows->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($create->columns[0]->column, $create->table->columns[0]);
    }

    public function testAssignmentsRetainBothOriginalColumnIdentitiesForASwap(): void
    {
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $empty = new Catalog(new SearchPath(new Name('main')), complete: false);
        $create = $reader->read($parser->parse('CREATE TABLE bar (a INTEGER, b INTEGER)'), $empty);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $catalog = new Catalog($empty->searchPath, tables: $create->table);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $destinations = new Scope($catalog, $target);
        $inputs = new Scope($catalog, $target);
        $assignments = (new MutationReader())->assignments($parser->parse('UPDATE bar SET a = b, b = a')->find('setlist')[0], $destinations, $inputs);
        self::assertInstanceOf(ResolvedColumn::class, $assignments[0]->column->resolution);
        self::assertSame($create->table->columns[0], $assignments[0]->column->resolution->column);
        $expression = $assignments[0]->expression;
        self::assertInstanceOf(ColumnReference::class, $expression);
        self::assertInstanceOf(ResolvedColumn::class, $expression->resolution);
        self::assertSame($create->table->columns[1], $expression->resolution->column);
        self::assertSame($inputs, $expression->scope);
        self::assertSame($destinations, $assignments[0]->column->scope);
    }

    public function testPredicateKeepsItsActualTargetOccurrence(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $scope = new Scope($catalog, $target);
        $tree = (new SqliteParser())->parse('DELETE FROM bar WHERE a');
        $predicate = (new MutationReader())->predicate($tree->find('where_opt_ret')[0], $scope);
        self::assertInstanceOf(ColumnReference::class, $predicate);
        self::assertSame($scope, $predicate->scope);
        self::assertSame('a', $predicate->name->value);
    }

    public function testReadUsesTheLastDestinationRequestButRetainsAllAssignments(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $tree = (new SqliteParser())->parse('UPDATE bar SET a = 1, b = 2, a = 3');
        $update = (new MutationReader())->read($tree->find('cmd')[0], $catalog);
        self::assertInstanceOf(SqliteUpdate::class, $update);
        self::assertCount(3, $update->assignments);
        self::assertSame([$update->assignments[1], $update->assignments[2]], $update->effectiveAssignments());
    }
}
