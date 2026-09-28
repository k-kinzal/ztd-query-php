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

    #[\PHPUnit\Framework\Attributes\TestWith([\SqlSemantics\Platform\MySql\Dialect::MySql, "CREATE TABLE t (a INT NOT NULL DEFAULT 1 COMMENT 'x', b ENUM('p','q') COLLATE utf8mb4_bin, c INT AS (a + 1) STORED, UNIQUE KEY u (b), CHECK (a > 0), FOREIGN KEY (a) REFERENCES p (id)) ENGINE=InnoDB"])]
    #[\PHPUnit\Framework\Attributes\TestWith([\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, "CREATE TABLE t (a INT NOT NULL DEFAULT 1 PRIMARY KEY, b TEXT COLLATE \"C\" CHECK (b <> ''), c INT GENERATED ALWAYS AS (a + 1) STORED, UNIQUE (b), FOREIGN KEY (a) REFERENCES p (id))"])]
    #[\PHPUnit\Framework\Attributes\TestWith([SqliteDialect::Sqlite, 'CREATE TABLE t (a INTEGER PRIMARY KEY DEFAULT 1, b TEXT COLLATE NOCASE UNIQUE, c INT AS (a + 1) STORED, CHECK (a > 0), FOREIGN KEY (a) REFERENCES p (id)) STRICT'])]
    public function testAnalyzeDeclaresTheValuesOfItsOwnCommand(Dialect $dialect, string $sql): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics($dialect);
        \Tests\Contract\Declared::assertValuesOfTheCommand($semantics->analyze($sql, [], Declarations::Partial));
        $users = $semantics->analyze('CREATE TABLE users (id INT, name VARCHAR(10) DEFAULT NULL)');
        $query = $semantics->analyze('SELECT name FROM users', [$users]);
        $reference = $query->resolution?->tables()[0];
        self::assertNotNull($reference);
        self::assertSame($users, $reference->declaration);
        $table = $reference->table;
        self::assertNotNull($table);
        $held = array_map(spl_object_id(...), iterator_to_array(\SqlSemantics\Statement\Traversal::walk($users->command), false));
        self::assertContains(spl_object_id($table->source), $held);
        self::assertContains(spl_object_id($table->columns[1]->source), $held);
    }

    public function testResolveMakesADependencyDeclareTheValuesOfItsOwnCommand(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        $analyzer = new Analyzer($language, ['main']);
        $orders = $analyzer->analyze('CREATE TABLE orders (id INTEGER PRIMARY KEY, total NUMERIC DEFAULT 0)');
        $tree = $language->parser()->parse('SELECT total FROM orders');
        $command = $language->values()->command($tree)[0];
        $resolution = $analyzer->resolve($tree, $command, [$orders]);
        $reference = $resolution->tables()[0];
        self::assertSame($orders, $reference->declaration);
        $held = array_map(spl_object_id(...), iterator_to_array(\SqlSemantics\Statement\Traversal::walk($orders->command), false));
        self::assertContains(spl_object_id($reference->table?->columns[1]->defaultExpression ?? $command), $held);
    }
}
