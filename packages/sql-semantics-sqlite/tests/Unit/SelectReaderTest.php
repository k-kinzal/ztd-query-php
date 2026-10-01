<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\SelectReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
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
    #[TestWith(['SELECT NULL'])]
    #[TestWith(['SELECT nUlL AS result'])]
    #[TestWith(['SELECT null, NULL'])]
    #[TestWith(['SELECT TRUE, false'])]
    #[TestWith(['SELECT TRUE AS result FROM bar'])]
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
}
