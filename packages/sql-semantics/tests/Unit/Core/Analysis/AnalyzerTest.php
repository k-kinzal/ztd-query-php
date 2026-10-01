<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Statement;
use Tests\Contract\FixtureCorpus;
use Tests\Contract\Resolved;

#[CoversClass(Analyzer::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(AnalysisException::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class AnalyzerTest extends TestCase
{
    public function testAnalyzeDoesNotNeedTableDeclarations(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['main']);
        $statement = $analyzer->analyze('DROP TABLE no_such_table');
        self::assertSame('DROP TABLE no_such_table', $statement->toString());
        self::assertNotSame($statement, $analyzer->analyze('DROP TABLE no_such_table'));
    }

    public function testAnalyzeRejectsInvalidSqlWithoutAnIncompleteStatement(): void
    {
        $this->expectException(AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyze('SELECT FROM');
    }

    public function testAnalyzeAllGivesOneStatementPerScriptStatement(): void
    {
        $statements = (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyzeAll("SELECT 1; SELECT ';' -- done");
        self::assertCount(2, $statements);
        self::assertSame('SELECT 1 ;', $statements[0]->toString());
        self::assertSame("SELECT ';' -- done", $statements[1]->toString());
    }

    public function testAnalyzeResolvesAnUnresolvedDependencyAgainstTheDependenciesBeforeIt(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['main']);
        $users = $analyzer->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $orders = $analyzer->analyze('CREATE TABLE orders (id INTEGER, user_id INTEGER REFERENCES users (id))');
        $query = $analyzer->analyze('SELECT * FROM orders', [$users, $orders]);
        self::assertSame($orders, $query->resolution?->references[0]->declaration);
        self::assertSame([$users, $orders], $query->resolution->dependencies);
    }

    public function testAnalyzeAllPassesPartialDeclarationsToEveryStatement(): void
    {
        $statements = (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyzeAll('SELECT * FROM audit; DROP TABLE audit', [], Declarations::Partial);
        self::assertSame(\SqlSemantics\Statement\ReferenceKind::Undeclared, $statements[0]->resolution?->references[0]->kind);
        self::assertSame(\SqlSemantics\Statement\ReferenceKind::Drop, $statements[1]->resolution?->references[0]->kind);
        self::assertNull($statements[1]->resolution->references[0]->declaration);
    }

    public function testSplitReportsSyntaxErrorsAsAnalysisErrors(): void
    {
        $this->expectException(AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->split('SELECT 1; SELECT FROM');
    }

    /**
     * @return iterable<string, array{Dialect, string, list<array{name: string, columns: list<string>}>}>
     */
    public static function providerFixtureDeclarations(): iterable
    {
        foreach (FixtureCorpus::cases() as $name => [$dialect, $sql, $error, $tables]) {
            if ($error === null) {
                yield $name => [$dialect, $sql, $tables];
            }
        }
    }

    /**
     * @return iterable<string, array{Dialect, string, class-string<AnalysisException|SemanticException>}>
     */
    public static function providerFixtureRejections(): iterable
    {
        foreach (FixtureCorpus::cases() as $name => [$dialect, $sql, $error]) {
            if ($error !== null) {
                yield $name => [$dialect, $sql, $error];
            }
        }
    }

    /**
     * @param list<array{name: string, columns: list<string>}> $expected
     */
    #[DataProvider('providerFixtureDeclarations')]
    public function testAnalyzeAllPreservesTheFixtureCorpus(Dialect $dialect, string $sql, array $expected): void
    {
        $analyzer = new Analyzer(new Language($dialect), $dialect->platform()->searchPath());
        $statements = $analyzer->analyzeAll($sql, dependencies: [], declarations: Declarations::Partial);
        $tables = array_merge(...array_map(static fn (Statement $statement): array => Resolved::of($statement)->declarations, $statements));
        self::assertSame($expected, array_map(static fn (TableDefinition $table): array => ['name' => $table->name, 'columns' => array_column($table->columns, 'name')], $tables));
        $rendered = array_map(static fn (Statement $statement): string => $statement->toString(), $statements);
        self::assertNotEmpty($rendered);
        self::assertSame($rendered, array_map(static fn (Statement $statement): string => $statement->toString(), $analyzer->analyzeAll(implode(' ', $rendered))));
        self::assertStringNotContainsString('SqlParser\\', serialize($statements));
    }

    /**
     * @param class-string<AnalysisException|SemanticException> $error
     */
    #[DataProvider('providerFixtureRejections')]
    public function testAnalyzeAllRejectsInvalidFixtureDeclarations(Dialect $dialect, string $sql, string $error): void
    {
        $this->expectException($error);
        (new Analyzer(new Language($dialect), $dialect->platform()->searchPath()))->analyzeAll($sql, dependencies: [], declarations: Declarations::Partial);
    }

    public function testAnalyzeReturnsColumnMeaningWithoutDeclarations(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $query = $analyzer->analyze('SELECT foo FROM bar');
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $query);
        $column = $query->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Expression\ColumnReference::class, $column);
        self::assertSame(\SqlSemantics\Statement\Type\Unresolved::MissingDeclaration, $column->type());
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\CandidateColumn::class, $column->resolution);
        self::assertSame([$query->fields()->scope->tables[0]], $column->resolution->possibilities);
        self::assertSame('bar', $query->fields()->scope->tables[0]->name->name->value);
        self::assertSame('SELECT foo FROM bar', $query->toString());
        self::assertTrue((new \SqlSemantics\Statement\SemanticGraph())->isSemanticOperation($query));
    }

    public function testAnalyzeRetainsExactSuppliedDeclarationObjects(): void
    {
        $column = new \SqlSemantics\Statement\Schema\Column(new \SqlSemantics\Statement\Identifier\Name('foo'), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::Integer));
        $table = new \SqlSemantics\Statement\Schema\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('bar')), $column);
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $query = $analyzer->analyze('SELECT foo FROM bar', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $query);
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\ResolvedColumn::class, $expression->resolution);
        self::assertSame($table, $expression->resolution->table);
        self::assertSame($column, $expression->resolution->column);
        self::assertSame($column->type, $expression->type());
        self::assertSame($table, $query->fields()->scope->tables[0]->declarations[0]);
    }

    public function testAnalyzeDistinguishesMissingColumnsFromMissingMetadata(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $table = $analyzer->analyze('CREATE TABLE bar (other INTEGER)');
        $query = $analyzer->analyze('SELECT foo FROM bar', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $query);
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Expression\ColumnReference::class, $expression);
        self::assertSame(\SqlSemantics\Statement\Reference\MissingColumn::Value, $expression->resolution);
        self::assertSame(\SqlSemantics\Statement\Type\Invalid::MissingColumn, $expression->type());
        $empty = $analyzer->analyze('SELECT foo FROM bar', []);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $empty);
        self::assertSame(\SqlSemantics\Statement\Type\Invalid::MissingColumn, $empty->field('foo')->expression->type());
    }

    public function testAnalyzeKeepsSafeProjectionUpdatesPersistent(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $table = $analyzer->analyze('CREATE TABLE bar (foo INTEGER, baz TEXT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Schema\Definition\SqliteCreateTable::class, $table);
        $query = $analyzer->analyze('SELECT foo FROM bar', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $query);
        $before = serialize($query);
        $field = new \SqlSemantics\Statement\Projection\Field(new \SqlSemantics\Statement\Expression\ColumnReference($query->fields()->scope, new \SqlSemantics\Statement\Identifier\Name('baz')));
        $changed = $query->withFields($query->fields()->addField($field));
        self::assertSame('SELECT foo, baz FROM bar', $changed->toString());
        self::assertSame($field, $changed->field('baz'));
        $expression = $field->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\ResolvedColumn::class, $expression->resolution);
        self::assertSame($table->table, $expression->resolution->table);
        self::assertSame($table->table->columns[1], $expression->resolution->column);
        self::assertSame($before, serialize($query));
    }

    #[\PHPUnit\Framework\Attributes\TestWith([false])]
    #[\PHPUnit\Framework\Attributes\TestWith([true])]
    public function testAnalyzeNeverSimulatesContextRequests(bool $reverse): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $table = $analyzer->analyze('CREATE TABLE bar (foo INTEGER)');
        self::assertInstanceOf(\SqlSemantics\Statement\Schema\Definition\SqliteCreateTable::class, $table);
        $requests = [$table, $analyzer->analyze('ALTER TABLE bar RENAME COLUMN foo TO baz'), $analyzer->analyze('DROP TABLE bar'), $analyzer->analyze('INSERT INTO bar VALUES (1)')];
        $context = $reverse ? array_reverse($requests) : $requests;
        $query = $analyzer->analyze('SELECT foo FROM bar', $context);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $query);
        $expression = $query->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\ResolvedColumn::class, $expression->resolution);
        self::assertSame($table->table, $expression->resolution->table);
        self::assertSame($table->table->columns[0], $expression->resolution->column);
        self::assertSame('foo', $expression->resolution->column->name->value);
    }

    public function testAnalyzeAllDoesNotMakeEarlierDeclarationsAvailable(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['temp', 'main']);
        $operations = $analyzer->analyzeAll('CREATE TABLE bar (foo INTEGER); SELECT foo FROM bar');
        self::assertCount(2, $operations);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $operations[1]);
        self::assertSame(\SqlSemantics\Statement\Type\Unresolved::MissingDeclaration, $operations[1]->field('foo')->expression->type());
        $complete = $analyzer->analyzeAll('CREATE TABLE bar (foo INTEGER); SELECT foo FROM bar', []);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\Select::class, $complete[1]);
        self::assertSame(\SqlSemantics\Statement\Type\Invalid::MissingColumn, $complete[1]->field('foo')->expression->type());
    }
}
