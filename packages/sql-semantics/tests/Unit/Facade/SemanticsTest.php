<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Writer;
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
#[UsesClass(Writer::class)]
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
        self::assertSame("\"select\" = 'it''s'", Writer::render($builder->compare($builder->column('select'), '=', $builder->string("it's"))));
    }

    public function testTypeReadsCatalogTypeText(): void
    {
        $type = (new Semantics(PostgreSql::PostgreSql))->type('numeric(7,2)')->type;
        self::assertSame(7, $type->precision);
        self::assertSame(2, $type->scale);
    }

    public function testDecodeLiteralReadsBuilderValuesWithoutChangingThem(): void
    {
        $semantics = new Semantics(MySqlDialect::MySql);
        $value = $semantics->builder()->string("it's a value");
        $before = serialize($value);
        self::assertSame("it's a value", $semantics->decodeLiteral($value)->value());
        self::assertSame($before, serialize($value));
    }


    #[TestWith([MySql::MySql])]
    #[TestWith([PostgreSql::PostgreSql])]
    #[TestWith([Sqlite::Sqlite])]
    public function testPartialDeclarationsKeepForeignNamesAndCanBeResolvedLater(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $sql = 'CREATE TABLE child(id INT PRIMARY KEY, parent_id INT, FOREIGN KEY(parent_id) REFERENCES parent(id))';
        $statement = $semantics->analyze($sql, dependencies: [], declarations: Declarations::Partial);
        $resolution = $statement->resolution ?? self::fail('Missing partial resolution');
        self::assertCount(1, $resolution->declarations);
        self::assertSame(['parent'], $resolution->references[1]->name);
        self::assertSame(ReferenceKind::Undeclared, $resolution->references[1]->kind);
        self::assertNull($resolution->references[1]->table);
        $before = serialize($statement);
        $parent = $semantics->analyze('CREATE TABLE parent(id INT PRIMARY KEY)', []);
        $resolved = $semantics->analyze($statement->toString(), [$parent]);
        self::assertSame(ReferenceKind::Dependency, $resolved->resolution?->references[1]->kind);
        self::assertSame($parent, $resolved->resolution->references[1]->declaration);
        self::assertSame($before, serialize($statement));
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        $semantics->analyze($sql, []);
    }

    public function testPartialScriptsRetainStatementOrderAndNeverInventTables(): void
    {
        $semantics = new Semantics(PostgreSql::PostgreSql);
        $statements = $semantics->analyzeAll('CREATE TABLE child(id INT REFERENCES parent(id)); SELECT * FROM child; SELECT * FROM absent', dependencies: [], declarations: Declarations::Partial);
        self::assertSame(ReferenceKind::Dependency, $statements[1]->resolution?->references[0]->kind);
        self::assertSame(ReferenceKind::Undeclared, $statements[2]->resolution?->references[0]->kind);
        self::assertSame([], $statements[2]->resolution->declarations);
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        $semantics->analyze('CREATE TABLE invalid(a INT, a INT)', dependencies: [], declarations: Declarations::Partial);
    }

    #[TestWith([MySql::MySql, "CREATE TABLE t(a VARCHAR(8) DEFAULT 'x')", "'x'"])]
    #[TestWith([MySql::MySql, 'CREATE TABLE t(a INT DEFAULT -2)', '- 2'])]
    #[TestWith([PostgreSql::PostgreSql, "CREATE TABLE t(a TEXT CONSTRAINT d DEFAULT 'x'::text)", "'x' :: text"])]
    #[TestWith([Sqlite::Sqlite, 'CREATE TABLE t(a INT DEFAULT -2)', '- 2'])]
    #[TestWith([Sqlite::Sqlite, 'CREATE TABLE t(a TEXT DEFAULT xyz)', "'xyz'"])]
    public function testDefaultsKeepTheirCompleteClauseAndTheirValue(Dialect $dialect, string $sql, string $value): void
    {
        $column = (new Semantics($dialect))->analyze($sql, [])->resolution?->declarations[0]->columns[0] ?? self::fail('Missing column');
        self::assertNotNull($column->defaultExpression);
        self::assertStringContainsString('DEFAULT', Writer::render($column->defaultExpression));
        self::assertNotNull($column->defaultValue);
        self::assertSame($value, Writer::render($column->defaultValue));
        self::assertSame($column->defaultValue, $column->withNullability(Nullability::NotNull)->defaultValue);
    }

    public function testTemporaryTablesAreReadableAndShadowTheMainNamespace(): void
    {
        $semantics = new Semantics(Sqlite::Sqlite);
        $main = $semantics->analyze('CREATE TABLE t(a INT)', []);
        $temp = $semantics->analyze('CREATE TEMP TABLE t(b TEXT)', [$main]);
        self::assertSame('temp', $temp->resolution?->declarations[0]->schema);
        self::assertSame('b', $semantics->analyze('SELECT * FROM t', [$main, $temp])->resolution?->references[0]->table?->columns[0]->name);
        self::assertSame('a', $semantics->analyze('SELECT * FROM main.t', [$main, $temp])->resolution?->references[0]->table?->columns[0]->name);
        $drop = $semantics->analyze('DROP TABLE t', [$main, $temp]);
        self::assertSame('a', $semantics->analyze('SELECT * FROM t', [$main, $temp, $drop])->resolution?->references[0]->table?->columns[0]->name);
    }

    public function testAnalyzeKeepsTemporaryTablesAheadOfAttachedDatabasesInPartialDeclarations(): void
    {
        $semantics = new Semantics(Sqlite::Sqlite, searchPath: new SearchPath('main', 'attached'));
        $statements = $semantics->analyzeAll('CREATE TABLE attached.t(a INT); CREATE TEMP TABLE t(b TEXT REFERENCES absent(id)); SELECT * FROM t; DROP TABLE t; SELECT * FROM t', [], Declarations::Partial);
        self::assertSame('temp', $statements[1]->resolution?->declarations[0]->schema);
        self::assertSame(ReferenceKind::Undeclared, $statements[1]->resolution->references[1]->kind);
        self::assertSame($statements[1], $statements[2]->resolution?->references[0]->declaration);
        self::assertSame($statements[0], $statements[4]->resolution?->references[0]->declaration);
        self::assertSame(['main', 'attached'], $semantics->searchPath());
    }

    public function testAnalyzePreservesAllNameValuesOfAnUndeclaredQualifiedReference(): void
    {
        $semantics = new Semantics(Sqlite::Sqlite);
        $reference = $semantics->analyze('SELECT * FROM attached.absent', [], Declarations::Partial)->resolution?->references[0] ?? self::fail('Missing reference');
        self::assertSame(ReferenceKind::Undeclared, $reference->kind);
        self::assertSame(['attached', 'absent'], $reference->name);
        self::assertCount(2, $reference->values);
        self::assertSame($reference->value, $reference->values[0]);
        self::assertNull($reference->table);
    }


    #[TestWith(['a COLLATE NOCASE'])]
    #[TestWith(['(a)'])]
    #[TestWith(["'a'"])]
    public function testAnalyzeReadsSqliteKeyColumnsAcceptedByTheEngine(string $key): void
    {
        $sql = 'CREATE TABLE t(a INT, b INT, PRIMARY KEY (' . $key . ', b))';
        (new PDO('sqlite::memory:'))->exec($sql);
        $table = (new Semantics(Sqlite::Sqlite))->analyze($sql, [])->resolution?->declarations[0] ?? self::fail('Missing table');
        self::assertSame(['a', 'b'], $table->constraints[0]->columns);
    }

    #[TestWith(['a + b'])]
    #[TestWith(['a || b'])]
    #[TestWith(['abs(a)'])]
    public function testAnalyzeRejectsSqliteKeyExpressionsRejectedByTheEngine(string $key): void
    {
        $sql = 'CREATE TABLE t(a INT, b INT, PRIMARY KEY (' . $key . ', b))';
        try {
            (new PDO('sqlite::memory:'))->exec($sql);
            self::fail('The engine accepted an expression in a table key.');
        } catch (PDOException $error) {
            self::assertStringContainsString('expressions prohibited', $error->getMessage());
        }
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        (new Semantics(Sqlite::Sqlite))->analyze($sql, []);
    }
}
