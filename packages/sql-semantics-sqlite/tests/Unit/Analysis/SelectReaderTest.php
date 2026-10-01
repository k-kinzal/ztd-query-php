<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\SelectReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\SqliteBinary;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(SelectReader::class)]
#[Medium]
final class SelectReaderTest extends TestCase
{
    #[TestWith(['SELECT foo FROM bar'])]
    #[TestWith(['SELECT foo FROM main.bar'])]
    #[TestWith(['SELECT DISTINCT foo FROM bar'])]
    #[TestWith(['SELECT ALL foo FROM bar'])]
    #[TestWith(['SELECT b.foo AS result FROM bar AS b WHERE b.foo'])]
    #[TestWith(['SELECT b.foo result FROM bar b'])]
    #[TestWith(['SELECT a.foo AS left_foo, b.foo AS right_foo FROM bar a, bar b'])]
    #[TestWith(["SELECT 'a''b\\c' AS text, X'00fFA1' AS bytes"])]
    #[TestWith(["SELECT '', x'', '日本語', x'00Ff41'"])]
    #[TestWith(['SELECT 1.0, 1_000.3_0E+0_2'])]
    #[TestWith(['SELECT CURRENT_DATE, CURRENT_TIME, CURRENT_TIMESTAMP'])]
    #[TestWith(['SELECT foo FROM bar LIMIT 2 OFFSET 1'])]
    #[TestWith(['SELECT foo FROM bar LIMIT 1, 2'])]
    #[TestWith(['SELECT NULL'])]
    #[TestWith(['SELECT nUlL AS result'])]
    #[TestWith(['SELECT null, NULL'])]
    #[TestWith(['SELECT TRUE, false'])]
    #[TestWith(['SELECT TRUE AS result FROM bar'])]
    #[TestWith(['SELECT 42 AS result'])]
    #[TestWith(['SELECT 1_000, 0X00_FF'])]
    #[TestWith(['SELECT 0xffffffffffffffff'])]
    #[TestWith(['SELECT 9223372036854775808'])]
    #[TestWith(['SELECT 0x10000000000000000'])]
    public function testReadProducesASemanticQueryWithStableReferences(string $sql): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $reader = new SelectReader();
        $parser = new SqliteParser();
        $query = $reader->read($parser->parse($sql)->find('select')[0], $catalog);
        self::assertSame($sql, $query->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($query));
        self::assertSame((new SemanticGraph())->fingerprint($query), (new SemanticGraph())->fingerprint($reader->read($parser->parse($query->toString())->find('select')[0], $catalog)));
    }

    public function testColumnDistinguishesMissingInformationFromAMissingField(): void
    {
        $parser = new SqliteParser();
        $reader = new SelectReader();
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = $reader->read($parser->parse('SELECT foo FROM bar')->find('select')[0], $catalog);
        self::assertSame(Unresolved::MissingDeclaration, $query->field('foo')->expression->type());
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $expression);
        self::assertInstanceOf(CandidateColumn::class, $expression->resolution);
        self::assertSame([$query->scope->tables[0]], $expression->resolution->possibilities);
    }

    public function testTablesAndColumnResolutionRetainOriginalDeclarationObjects(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $reader = new SelectReader();
        $parser = new SqliteParser();
        $query = $reader->read($parser->parse('SELECT foo, absent FROM bar')->find('select')[0], $catalog);
        $reference = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertInstanceOf(ResolvedColumn::class, $reference->resolution);
        self::assertSame($table, $reference->resolution->table);
        self::assertSame($column, $reference->resolution->column);
        self::assertSame($column->type, $reference->type());
        self::assertSame(Invalid::MissingColumn, $query->field('absent')->expression->type());
    }

    public function testProjectionKeepsDuplicateOutputPositions(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT foo, foo FROM bar')->find('select')[0], $catalog);
        self::assertCount(2, $query->fields()->items);
        self::assertNotSame($query->fields()->items[0], $query->fields()->items[1]);
        self::assertSame('foo', $query->fields()->items[0]->name->value);
        self::assertSame('foo', $query->fields()->items[1]->name->value);
    }

    public function testAliasSeparatesRelationVisibilityFromProjectionLabels(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT b.foo AS result FROM bar b')->find('select')[0], $catalog);
        self::assertSame('b', $query->scope->tables[0]->visibleName()->value);
        self::assertSame('result', $query->fields()->items[0]->name->value);
        self::assertSame('b.foo', $query->fields()->items[0]->expression->toString());
    }

    public function testExpressionDistinguishesAKnownNullFromAMissingColumnDeclaration(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT null, foo FROM bar')->find('select')[0], $catalog);
        self::assertSame(NullDomain::Null, $query->field('null')->expression->type());
        self::assertSame(Nullability::AlwaysNull, $query->field('null')->expression->nullability());
        self::assertSame(Unresolved::MissingDeclaration, $query->field('foo')->expression->type());
    }

    public function testExpressionKeepsBooleanColumnResolutionAheadOfItsLiteralAlternative(): void
    {
        $column = new Column(new Name('true'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, $table);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT TRUE FROM bar')->find('select')[0], $catalog);
        $expression = $query->field('true')->expression;
        self::assertInstanceOf(BooleanReference::class, $expression);
        self::assertInstanceOf(ResolvedColumn::class, $expression->column->resolution);
        self::assertSame($column, $expression->column->resolution->column);
        self::assertSame($column->name, $query->field('true')->name);
        self::assertSame($column->type, $expression->type());
    }

    public function testExpressionKeepsExactNumbersWithoutMachineRounding(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT 0xffffffffffffffff AS signed, 0x10000000000000000 AS invalid')->find('select')[0], $catalog);
        $literal = $query->field('signed')->expression;
        self::assertInstanceOf(SqliteInteger::class, $literal);
        self::assertSame('-1', $literal->value->value());
        self::assertSame(Invalid::IntegerLiteralOverflow, $query->field('invalid')->expression->type());
    }

    #[DataProvider('providerBinaryExpressions')]
    #[DataProvider('providerComputedExpressions')]
    public function testReadPreservesComputedOutputLabelsWhenRenderingGroupedOperands(string $sql): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $reader = new SelectReader();
        $parser = new SqliteParser();
        $query = $reader->read($parser->parse($sql)->find('select')[0], $catalog);
        $db = new PDO('sqlite::memory:');
        $original = $db->query($sql);
        $rebuilt = $db->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame(array_map($original->getColumnMeta(...), range(0, $original->columnCount() - 1)), array_map($rebuilt->getColumnMeta(...), range(0, $rebuilt->columnCount() - 1)));
        self::assertSame($original->fetch(PDO::FETCH_NUM), $rebuilt->fetch(PDO::FETCH_NUM));
        self::assertSame((new SemanticGraph())->fingerprint($query), (new SemanticGraph())->fingerprint($reader->read($parser->parse($query->toString())->find('select')[0], $catalog)));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerComputedExpressions(): array
    {
        return [
            ['SELECT ((TRUE)), (null), ((1))'],
            ['SELECT 1+2*3, (1+2)*3'],
            ['SELECT 2 IS TRUE, 2 IS (+TRUE)'],
            ['SELECT NULL = NULL, NULL IS NULL'],
            ['SELECT 1 BETWEEN 2 AND NULL, NULL IN ()'],
            ['SELECT absent NOT IN ()'],
            ['SELECT 1 IN (NULL, 1)'],
            ['SELECT 0 AND absent, absent AND 0'],
            ['SELECT -1'],
            ['SELECT -(+3), +NULL'],
            ['SELECT NOT 1 AS result'],
            ['SELECT ~ 1, +2'],
            ['SELECT 1 AS answer WHERE answer'],
            ['SELECT 1 AS n, 2 AS n WHERE n = 1'],
            ['SELECT 1+2, 4 AS "1+2" WHERE "1+2" = 4'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBinaryExpressions(): iterable
    {
        $operands = ['NULL', '0', '1', '-1', '1.5', '1e999', "'x'", "X'01'"];
        $operators = ['+', '-', '*', '/', '%', '&', '|', '<<', '>>', '||', '<', '>', '<=', '>=', '=', '==', '!=', '<>', 'IS', 'IS NOT', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', 'AND', 'OR'];
        foreach ($operators as $operator) {
            foreach ($operands as $left) {
                foreach ($operands as $right) {
                    $sql = 'SELECT ' . $left . ' ' . $operator . ' ' . $right;
                    yield $sql => [$sql];
                }
            }
        }
    }

    public function testReadResolvesAWhereAliasToItsActualProjectedField(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, $table);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT foo + 1 AS n FROM bar WHERE N > 2')->find('select')[0], $catalog);
        self::assertInstanceOf(SqliteBinary::class, $query->where);
        self::assertInstanceOf(AliasReference::class, $query->where->left);
        self::assertSame($query->field('n'), $query->where->left->field);
        self::assertSame($query->field('n')->expression->references(), $query->where->left->references());
        self::assertInstanceOf(ResolvedColumn::class, $query->where->left->references()[0]->resolution);
        self::assertSame($column, $query->where->left->references()[0]->resolution->column);
    }

    #[TestWith(['SELECT foo + 1 AS n FROM bar WHERE n > 2'])]
    #[TestWith(['SELECT foo + 1 AS foo FROM bar WHERE foo > 2'])]
    #[TestWith(['SELECT foo AS n, 10 AS n FROM bar WHERE n > 2'])]
    public function testReadPreservesAliasAndInputColumnPriorityOnTheDatabase(string $sql): void
    {
        $table = new Table(new QualifiedName(new Name('bar')), new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer)));
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, $table);
        $reader = new SelectReader();
        $parser = new SqliteParser();
        $query = $reader->read($parser->parse($sql)->find('select')[0], $catalog);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER); INSERT INTO bar VALUES(1), (2), (3)');
        $original = $db->query($sql);
        $rebuilt = $db->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_NAMED), $rebuilt->fetchAll(PDO::FETCH_NAMED));
        self::assertSame((new SemanticGraph())->fingerprint($query), (new SemanticGraph())->fingerprint($reader->read($parser->parse($query->toString())->find('select')[0], $catalog)));
    }

    public function testReadKeepsAnInputColumnAndAliasAsAlternativesWhenTheTableIsUndeclared(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, complete: false);
        $query = (new SelectReader())->read((new SqliteParser())->parse('SELECT foo + 1 AS n FROM bar WHERE n')->find('select')[0], $catalog);
        self::assertInstanceOf(ColumnOrAlias::class, $query->where);
        self::assertSame(Unresolved::MissingDeclaration, $query->where->type());
        self::assertSame($query->field('n'), $query->where->alias->field);
        self::assertInstanceOf(CandidateColumn::class, $query->where->column->resolution);
        self::assertSame([$query->scope->tables[0]], $query->where->column->resolution->possibilities);
    }

    #[TestWith(['LIMIT 2'])]
    #[TestWith(['LIMIT 2 OFFSET 1'])]
    #[TestWith(['LIMIT 1, 2'])]
    #[TestWith(['LIMIT 1+1 OFFSET 1+0'])]
    #[TestWith(['LIMIT -1 OFFSET 2'])]
    #[TestWith(['LIMIT -2, -1'])]
    #[TestWith(['LIMIT 1.0'])]
    #[TestWith(["LIMIT '2'"])]
    public function testReadPreservesRowRestrictionExpressionsAndTheirSeparateScope(string $clause): void
    {
        $table = new Table(new QualifiedName(new Name('bar')), new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer)));
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, $table);
        $sql = 'SELECT foo FROM bar ' . $clause;
        $reader = new SelectReader();
        $parser = new SqliteParser();
        $query = $reader->read($parser->parse($sql)->find('select')[0], $catalog);
        self::assertNotNull($query->limit);
        self::assertNotSame($query->scope, $query->limit->scope);
        self::assertSame($query->scope->catalog, $query->limit->scope->catalog);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER); INSERT INTO bar VALUES(1),(2),(3),(4)');
        $original = $db->query($sql);
        $rebuilt = $db->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame((new SemanticGraph())->fingerprint($query), (new SemanticGraph())->fingerprint($reader->read($parser->parse($query->toString())->find('select')[0], $catalog)));
    }
}
