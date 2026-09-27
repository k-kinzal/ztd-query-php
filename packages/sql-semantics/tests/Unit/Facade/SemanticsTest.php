<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use Tests\Contract\Resolved;

#[CoversClass(Semantics::class)]
#[UsesClass(Mode::class)]
#[UsesClass(SearchPath::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Resolver::class)]
#[UsesClass(\SqlSemantics\Statement\Resolution::class)]
#[UsesClass(Reference::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Composition::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    public function testAnalyzeAcceptsTheDialectContract(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        self::assertSame('DROP TABLE example', $semantics->analyze('DROP TABLE example')->toString());
    }

    public function testLanguageAnswersTheResolvedReleaseModeAndParameterSyntax(): void
    {
        $mode = Mode::fromString('NO_BACKSLASH_ESCAPES');
        $semantics = new Semantics(MySqlDialect::MySql, 'mysql-8.0.44', $mode, Parameters::Named);
        self::assertSame('mysql-8.0.44', $semantics->language()->version);
        self::assertSame($mode, $semantics->language()->mode);
        self::assertSame(Parameters::Named, $semantics->language()->parameters);
    }

    public function testAnalyzeReadsUnderTheMode(): void
    {
        $sql = "SELECT \"x\" FROM t WHERE y = 'a\\'";
        self::assertSame($sql, (new Semantics(MySqlDialect::MySql, null, Mode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES')))->analyze($sql)->toString());
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Semantics(MySqlDialect::MySql))->analyze($sql);
    }

    public function testAnalyzeReadsNamedPlaceholdersOnRequest(): void
    {
        $sql = 'SELECT id FROM users WHERE id = :id AND status = ?';
        self::assertSame($sql, (new Semantics(MySqlDialect::MySql, parameters: Parameters::Named))->analyze($sql)->toString());
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Semantics(MySqlDialect::MySql))->analyze($sql);
    }

    public function testSearchPathAnswersTheSchemasOfTheSessionOrTheServersDefault(): void
    {
        self::assertSame([''], (new Semantics(MySqlDialect::MySql))->searchPath());
        self::assertSame(['app'], (new Semantics(MySqlDialect::MySql, searchPath: new SearchPath('app')))->searchPath());
        self::assertSame(['main'], (new Semantics(SqliteDialect::Sqlite))->searchPath());
    }

    public function testAnalyzeReadsUnqualifiedNamesInTheCurrentDatabaseAndNamesUndeclaredTablesOnRequest(): void
    {
        $semantics = new Semantics(MySqlDialect::MySql, searchPath: new SearchPath('app'));
        $users = $semantics->analyze('CREATE TABLE users (id INT)');
        $query = $semantics->analyze('SELECT * FROM app.users JOIN audit_log', [$users], Declarations::Partial);
        self::assertSame([ReferenceKind::Dependency, ReferenceKind::Undeclared], array_map(static fn (Reference $reference): ReferenceKind => $reference->kind, $query->resolution->references ?? []));
        self::assertSame(ReferenceKind::Undeclared, $semantics->analyzeAll('SELECT * FROM audit_log;', [$users], Declarations::Partial)[0]->resolution?->references[0]->kind);
    }

    public function testAnalyzeAllGivesOneStatementPerScriptStatement(): void
    {
        $semantics = new Semantics(MySqlDialect::MySql);
        $script = "SELECT 1; CREATE PROCEDURE p() BEGIN SELECT ';'; END; SELECT 2 -- end";
        self::assertSame(['SELECT 1;', " CREATE PROCEDURE p() BEGIN SELECT ';'; END;", ' SELECT 2 -- end'], $semantics->split($script));
        $statements = $semantics->analyzeAll($script);
        self::assertCount(3, $statements);
        self::assertSame('SELECT 2 -- end', $statements[2]->toString());
    }

    public function testSplitKeepsSemicolonsInsideStringsAndComments(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        self::assertSame(["SELECT ';' -- ;\n;", ' SELECT 2'], $semantics->split("SELECT ';' -- ;\n; SELECT 2"));
        self::assertSame([], $semantics->split('   '));
    }

    public function testAnalyzeResolvesAgainstDependenciesAndStructuresOnlyWithoutThem(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNull($users->resolution);
        $query = $semantics->analyze('SELECT name FROM users WHERE id = 1', [$users]);
        self::assertSame('users', Resolved::of($query)->tables()[0]->table?->name);
        self::assertSame($users, Resolved::of($query)->tables()[0]->declaration);
        self::assertNull($query->withCommand($query->command)->resolution);
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        $semantics->analyze('SELECT name FROM users', []);
    }

    public function testAnalyzeAllResolvesEachStatementAgainstTheOnesBeforeIt(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $statements = $semantics->analyzeAll('CREATE TABLE t (a INTEGER); INSERT INTO t VALUES (1); DROP TABLE t', []);
        self::assertCount(3, $statements);
        self::assertSame($statements[0], $statements[1]->resolution?->references[0]->declaration);
        self::assertSame(ReferenceKind::Drop, $statements[2]->resolution?->references[0]->kind);
        self::assertNull($semantics->analyzeAll('SELECT 1; SELECT 2')[1]->resolution);
    }

    public function testBuilderComposesValuesOfTheLanguage(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame("\"select\" = 'it''s'", \SqlSemantics\Statement\Writer::render($builder->compare($builder->column('select'), '=', $builder->string("it's"))));
    }
}
