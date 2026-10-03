<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Analysis\Expression\SubqueryReader;
use SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteScalarSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\AliasDependencies;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Reference\OuterLookup;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(SubqueryReader::class)]
#[Medium]
final class SubqueryReaderTest extends TestCase
{
    public function testReadLeavesAnEnclosingOperatorToItsOwnReader(): void
    {
        $source = Tree::outer((new SqliteParser())->parse('SELECT 1 + (SELECT 2)'), ['expr'])[0];
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        self::assertNull((new SubqueryReader())->read($source, $scope, new ExpressionReader(), $scope));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (['NULL', '0', '1', "'text'", "X'FF'", 'id', 'outer_table.id'] as $value) {
            foreach (['', ' WHERE 0', ' WHERE 1', " WHERE local = 'inner'"] as $where) {
                $query = 'SELECT ' . $value . ' FROM inner_table' . $where;
                yield 'scalar ' . $query => ['SELECT (' . $query . ') FROM outer_table'];
                yield 'exists ' . $query => ['SELECT EXISTS (' . $query . ') FROM outer_table'];
                foreach (['NULL', '1', "'text'"] as $subject) {
                    yield $subject . ' in ' . $query => ['SELECT ' . $subject . ' IN (' . $query . ') FROM outer_table'];
                    yield $subject . ' not in ' . $query => ['SELECT ' . $subject . ' NOT IN (' . $query . ') FROM outer_table'];
                }
            }
        }
        foreach ([
            'SELECT 1+2, 7 AS a WHERE EXISTS (SELECT a)',
            'SELECT 1+2, 7 AS a WHERE EXISTS (SELECT a FROM inner_table)',
            'SELECT (VALUES (1), (NULL))',
            'SELECT 1 IN (VALUES (2), (NULL))',
            'SELECT EXISTS (SELECT NULL, 1)',
            'SELECT (SELECT (SELECT outer_table.id FROM inner_table)) FROM outer_table',
            'SELECT id AS x FROM outer_table WHERE EXISTS (SELECT x FROM inner_table)',
            "SELECT id AS x FROM outer_table WHERE EXISTS (SELECT local AS x FROM inner_table WHERE x = 'inner')",
            'SELECT 1 WHERE EXISTS (SELECT 2 AS x WHERE EXISTS (SELECT x))',
            'SELECT id AS local FROM outer_table WHERE EXISTS (SELECT local FROM inner_table)',
            "SELECT id FROM outer_table WHERE EXISTS (SELECT local AS id FROM inner_table WHERE id = 'inner')",
            'SELECT id AS answer FROM outer_table WHERE EXISTS (SELECT 1 FROM inner_table WHERE answer = 7)',
            'SELECT 1 LIMIT (SELECT 1)',
            'SELECT (SELECT 1 LIMIT (SELECT 1))',
            'SELECT CASE WHEN EXISTS (SELECT NULL) THEN (SELECT 2) END',
        ] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerQueries')]
    public function testReadPreservesDatabaseRowsOutputNamesAndSemanticGraph(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $schema = [$semantics->analyze('CREATE TABLE outer_table(id INTEGER, local TEXT)'), $semantics->analyze('CREATE TABLE inner_table(local TEXT)')];
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE outer_table(id INTEGER, local TEXT); CREATE TABLE inner_table(local TEXT); INSERT INTO outer_table VALUES(7, 'outer'),(8, NULL); INSERT INTO inner_table VALUES('inner'),(NULL)");
        $operation = $semantics->analyze($sql, $schema);
        $rebuilt = $operation->toString();
        $originalRows = $pdo->query($sql);
        $rebuiltRows = $pdo->query($rebuilt);
        self::assertInstanceOf(PDOStatement::class, $originalRows);
        self::assertInstanceOf(PDOStatement::class, $rebuiltRows);
        self::assertSame($originalRows->fetchAll(PDO::FETCH_ASSOC), $rebuiltRows->fetchAll(PDO::FETCH_ASSOC));
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($rebuilt, $schema)));
    }

    public function testReadKeepsExactOuterDeclarationIdentity(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $declaration = $semantics->analyze('CREATE TABLE outer_table(id INTEGER)');
        self::assertInstanceOf(SqliteCreateTable::class, $declaration);
        $query = $semantics->analyze('SELECT (SELECT id) FROM outer_table', [$declaration]);
        self::assertInstanceOf(Select::class, $query);
        $expression = $query->fields()->items[0]->expression;
        self::assertInstanceOf(SqliteScalarSubquery::class, $expression);
        $reference = $expression->references()[0];
        self::assertInstanceOf(ResolvedColumn::class, $reference->resolution);
        self::assertSame($declaration->table, $reference->resolution->table);
        self::assertSame($declaration->table->columns[0], $reference->resolution->column);
        self::assertSame($query->scope->tables[0], $reference->resolution->relation);
        self::assertNotSame($query->scope, $reference->scope);
    }

    public function testReadKeepsAnOuterAliasAsAReferenceToTheExactField(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 7 AS answer WHERE EXISTS (SELECT answer)', []);
        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(SqliteExists::class, $query->where);
        $reference = $query->where->references()[0];
        self::assertInstanceOf(NamedAlias::class, $reference->resolution);
        self::assertSame($query->field('answer'), $reference->resolution->field);
        self::assertSame('answer', $reference->toString());
        $newFields = $query->fields()->addField(new Field(new \SqlSemantics\Statement\Expression\NullConstant(), new Name('extra')));
        self::assertSame($query->where, $query->withFields($newFields)->where);
        self::assertFalse((new AliasDependencies())->preserved($query->where, new \SqlSemantics\Statement\Projection\Fields($query->scope)));
    }

    public function testReadKeepsOuterLookupConditionalWhenInnerDeclarationsAreMissing(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 7 AS answer WHERE EXISTS (SELECT answer FROM undeclared)');
        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(SqliteExists::class, $query->where);
        $reference = $query->where->references()[0];
        self::assertInstanceOf(CandidateColumn::class, $reference->resolution);
        $fallback = $reference->resolution->possibilities[1];
        self::assertInstanceOf(OuterLookup::class, $fallback);
        self::assertInstanceOf(NamedAlias::class, $fallback->resolution);
        self::assertSame($query->field('answer'), $fallback->resolution->field);
    }

    #[TestWith(['SELECT 3 AS x, (SELECT x)', 1])]
    #[TestWith(['SELECT (SELECT 1 LIMIT outer_table.id) FROM outer_table', 0])]
    public function testReadDoesNotInventAliasOrLimitVisibility(string $sql, int $position): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $schema = [$semantics->analyze('CREATE TABLE outer_table(id INTEGER)')];
        $query = $semantics->analyze($sql, $schema);
        self::assertInstanceOf(Select::class, $query);
        self::assertSame(Invalid::MissingColumn, $query->fields()->items[$position]->expression->type());
    }

    #[TestWith(['SELECT (SELECT 1, 2)', Invalid::ScalarSubqueryWidth])]
    #[TestWith(['SELECT 1 IN (VALUES (1, 2))', Invalid::ScalarSubqueryWidth])]
    #[TestWith(['SELECT EXISTS (VALUES (1),(2,3))', Invalid::InconsistentRowWidth])]
    #[TestWith(['SELECT EXISTS (SELECT missing)', Invalid::MissingColumn])]
    public function testReadRetainsSemanticallyInvalidButGrammaticalQueries(string $sql, Invalid $expected): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze($sql, []);
        self::assertInstanceOf(Select::class, $query);
        self::assertSame($expected, $query->fields()->items[0]->expression->type());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($query));
    }
    #[TestWith(['UPDATE outer_table SET id = (SELECT id + 1) WHERE EXISTS (SELECT 1 FROM inner_table)'])]
    #[TestWith(['DELETE FROM outer_table WHERE id IN (SELECT outer_table.id FROM inner_table)'])]
    #[TestWith(['INSERT INTO inner_table(local) VALUES ((SELECT local FROM outer_table LIMIT 1))'])]
    #[TestWith(['INSERT INTO inner_table(local) SELECT (SELECT local FROM outer_table LIMIT 1)'])]
    public function testReadPreservesSubqueryMeaningInsideWrites(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $schema = [$semantics->analyze('CREATE TABLE outer_table(id INTEGER, local TEXT)'), $semantics->analyze('CREATE TABLE inner_table(local TEXT)')];
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE outer_table(id INTEGER, local TEXT); CREATE TABLE inner_table(local TEXT); INSERT INTO outer_table VALUES(7, 'outer'),(8, NULL); INSERT INTO inner_table VALUES('inner'),(NULL)");
        $operation = $semantics->analyze($sql, $schema);
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $original = $pdo->query('SELECT id, local FROM outer_table UNION ALL SELECT NULL, local FROM inner_table');
        self::assertInstanceOf(PDOStatement::class, $original);
        $expected = $original->fetchAll(PDO::FETCH_NUM);
        $pdo->rollBack();
        $pdo->exec($operation->toString());
        $rebuilt = $pdo->query('SELECT id, local FROM outer_table UNION ALL SELECT NULL, local FROM inner_table');
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($expected, $rebuilt->fetchAll(PDO::FETCH_NUM));
        $graph = new SemanticGraph();
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($operation->toString(), $schema)));
    }

}
