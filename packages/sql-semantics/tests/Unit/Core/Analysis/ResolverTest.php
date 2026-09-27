<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\NameSite;
use SqlSemantics\Core\Analysis\Relations;
use SqlSemantics\Core\Analysis\Resolver;
use SqlSemantics\Core\Analysis\Scope;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SearchPath;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Resolved;
use Tests\Contract\Resolving;

#[CoversClass(Resolver::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[UsesClass(NameSite::class)]
#[UsesClass(Relations::class)]
#[UsesClass(Scope::class)]
#[UsesClass(SearchPath::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Scopes::class)]
#[UsesClass(\SqlSemantics\Core\Policy\WithVisibility::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\NameSites::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Forms::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Resolution::class)]
#[UsesClass(Reference::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[Medium]
final class ResolverTest extends TestCase
{
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testResolveTellsDependenciesFromCommonTableExpressionsInEveryDialect(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(10))');
        $query = $semantics->analyze('WITH recent AS (SELECT id FROM users) SELECT u.name FROM users u JOIN recent ON recent.id = u.id', [$users]);
        $kinds = array_map(static fn (Reference $reference): string => implode('.', $reference->name) . ':' . $reference->kind->name, Resolved::of($query)->references);
        sort($kinds);
        self::assertSame(['recent:CommonTableExpression', 'recent:CommonTableExpression', 'users:Dependency', 'users:Dependency'], $kinds);
        self::assertCount(2, Resolved::of($query)->tables());
        self::assertSame($users, Resolved::of($query)->tables()[0]->declaration);
        self::assertSame('users', Resolved::of($query)->tables()[0]->table?->name);
        self::assertSame('users', Writer::render(Resolved::of($query)->tables()[0]->value));
        self::assertSame([$users], Resolved::of($query)->dependencies);
    }

    /**
     * @return iterable<string, array{Dialect, string, string}>
     */
    public static function providerScopedStatements(): iterable
    {
        $selfReference = 'WITH users AS (SELECT * FROM users WHERE id > 1) SELECT * FROM users';
        yield 'mysql: a plain expression does not see itself' => [MySqlDialect::MySql, $selfReference, 'users:CTE users:Dependency users:CTE'];
        yield 'postgresql: a plain expression does not see itself' => [PostgreSqlDialect::PostgreSql, $selfReference, 'users:CTE users:Dependency users:CTE'];
        yield 'sqlite: an expression always sees itself' => [SqliteDialect::Sqlite, $selfReference, 'users:CTE users:CTE users:CTE'];
        $forward = "WITH a AS (SELECT * FROM b), b AS (SELECT 'x' AS x) SELECT * FROM a";
        yield 'mysql: a later expression is not visible' => [MySqlDialect::MySql, $forward, 'a:CTE b:Dependency b:CTE a:CTE'];
        yield 'postgresql: a later expression is not visible in a plain clause' => [PostgreSqlDialect::PostgreSql, $forward, 'a:CTE b:Dependency b:CTE a:CTE'];
        yield 'sqlite: a later expression is visible' => [SqliteDialect::Sqlite, $forward, 'a:CTE b:CTE b:CTE a:CTE'];
        $recursiveForward = "WITH RECURSIVE a AS (SELECT * FROM b), b AS (SELECT 'x' AS x) SELECT * FROM a";
        yield 'mysql: a later expression is not visible in a recursive clause' => [MySqlDialect::MySql, $recursiveForward, 'a:CTE b:Dependency b:CTE a:CTE'];
        yield 'postgresql: a later expression is visible in a recursive clause' => [PostgreSqlDialect::PostgreSql, $recursiveForward, 'a:CTE b:CTE b:CTE a:CTE'];
        yield 'sqlite: a later expression is visible in a recursive clause' => [SqliteDialect::Sqlite, $recursiveForward, 'a:CTE b:CTE b:CTE a:CTE'];
        $recursive = 'WITH RECURSIVE r AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM r WHERE n < 3) SELECT * FROM r';
        $preceding = "WITH b AS (SELECT 'x' AS x), a AS (SELECT * FROM b) SELECT * FROM a";
        $shadowed = "WITH b AS (SELECT 'outer' AS x) SELECT * FROM (WITH b AS (SELECT 'inner' AS x) SELECT * FROM b) AS s, b";
        $subquery = "WITH b AS (SELECT 'outer' AS x) SELECT (SELECT x FROM b) AS y";
        $inner = "SELECT * FROM (WITH b AS (SELECT 'inner' AS x) SELECT * FROM b) AS s WHERE EXISTS (SELECT * FROM b)";
        $insertQuery = "INSERT INTO users WITH users AS (SELECT 8 AS id, 'y' AS v) SELECT * FROM users";
        foreach ([MySqlDialect::MySql, PostgreSqlDialect::PostgreSql, SqliteDialect::Sqlite] as $dialect) {
            yield $dialect->name . ': a recursive expression sees itself' => [$dialect, $recursive, 'r:CTE r:CTE r:CTE'];
            yield $dialect->name . ': an earlier expression is visible' => [$dialect, $preceding, 'b:CTE a:CTE b:CTE a:CTE'];
            yield $dialect->name . ': an inner expression shadows an outer one' => [$dialect, $shadowed, 'b:CTE b:CTE b:CTE b:CTE'];
            yield $dialect->name . ': a subquery sees the clause of its query' => [$dialect, $subquery, 'b:CTE b:CTE'];
            yield $dialect->name . ': an inner clause is not visible outside its query' => [$dialect, $inner, 'b:CTE b:CTE b:Dependency'];
            yield $dialect->name . ': a clause of the inserted query does not reach the target' => [$dialect, $insertQuery, 'users:Dependency users:CTE users:CTE'];
        }
        $delete = 'WITH users AS (SELECT 9 AS id) DELETE FROM users WHERE id IN (SELECT id FROM users)';
        $update = "WITH users AS (SELECT 1 AS id) UPDATE users SET v = 'u' WHERE id IN (SELECT id FROM users)";
        yield 'mysql: the target of DELETE sees the clause' => [MySqlDialect::MySql, $delete, 'users:CTE users:CTE users:CTE'];
        yield 'mysql: the target of UPDATE sees the clause' => [MySqlDialect::MySql, $update, 'users:CTE users:CTE users:CTE'];
        foreach ([PostgreSqlDialect::PostgreSql, SqliteDialect::Sqlite] as $dialect) {
            yield $dialect->name . ': the target of DELETE is a table' => [$dialect, $delete, 'users:CTE users:Dependency users:CTE'];
            yield $dialect->name . ': the target of UPDATE is a table' => [$dialect, $update, 'users:CTE users:Dependency users:CTE'];
            yield $dialect->name . ': the target of INSERT is a table' => [$dialect, 'WITH users AS (SELECT 9 AS id, 1 AS v) INSERT INTO users SELECT * FROM users', 'users:CTE users:Dependency users:CTE'];
        }
        yield 'postgresql: the target of MERGE is a table and its source sees the clause' => [PostgreSqlDialect::PostgreSql, 'WITH users AS (SELECT 7 AS id) MERGE INTO users USING users AS s ON users.id = s.id WHEN MATCHED THEN DO NOTHING', 'users:CTE users:Dependency users:CTE'];
        yield 'mysql: an expression name compares as a table name' => [MySqlDialect::MySql, 'WITH B AS (SELECT 1 AS x) SELECT * FROM b', 'B:CTE b:Dependency'];
        yield 'sqlite: an expression name compares as a table name' => [SqliteDialect::Sqlite, 'WITH B AS (SELECT 1 AS x) SELECT * FROM b', 'B:CTE b:CTE'];
        yield 'mysql: a qualified name is never an expression' => [MySqlDialect::MySql, 'WITH b AS (SELECT 1 AS x) SELECT * FROM other.b', 'b:CTE other.b:Dependency'];
    }

    #[DataProvider('providerScopedStatements')]
    public function testResolveScopesCommonTableExpressionsAsTheServerDoes(Dialect $dialect, string $sql, string $expected): void
    {
        $semantics = new Semantics($dialect);
        $dependencies = [
            $semantics->analyze('CREATE TABLE users (id INTEGER, v VARCHAR(20))'),
            $semantics->analyze('CREATE TABLE b (x VARCHAR(20))'),
            $semantics->analyze('CREATE TABLE other.b (x VARCHAR(20))'),
        ];
        $references = Resolved::of($semantics->analyze($sql, $dependencies))->references;
        $kinds = array_map(static fn (Reference $reference): string => implode('.', $reference->name) . ':' . ($reference->kind === ReferenceKind::CommonTableExpression ? 'CTE' : $reference->kind->name), $references);
        self::assertSame($expected, implode(' ', $kinds));
    }

    #[TestWith([MySqlDialect::MySql, 'INSERT INTO users (id) VALUES (1)'])]
    #[TestWith([MySqlDialect::MySql, "UPDATE users SET name = 'x' WHERE id = 1"])]
    #[TestWith([MySqlDialect::MySql, 'DELETE FROM users WHERE id = 1'])]
    #[TestWith([MySqlDialect::MySql, 'DELETE u FROM users u JOIN users v ON u.id = v.id'])]
    #[TestWith([MySqlDialect::MySql, 'TRUNCATE TABLE users'])]
    #[TestWith([MySqlDialect::MySql, 'ALTER TABLE users ADD COLUMN x INT'])]
    #[TestWith([MySqlDialect::MySql, 'CREATE INDEX i ON users (id)'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'INSERT INTO users AS u (id) VALUES (1)'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, "UPDATE users SET name = 'x'"])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'DELETE FROM users WHERE id = 1'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'TRUNCATE users'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'ALTER TABLE users ADD COLUMN x int'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'SELECT id FROM ONLY users'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'MERGE INTO users u USING users s ON u.id = s.id WHEN MATCHED THEN DELETE'])]
    #[TestWith([SqliteDialect::Sqlite, 'INSERT INTO users (id) VALUES (1)'])]
    #[TestWith([SqliteDialect::Sqlite, "UPDATE users SET name = 'x'"])]
    #[TestWith([SqliteDialect::Sqlite, 'DELETE FROM main.users WHERE id = 1'])]
    #[TestWith([SqliteDialect::Sqlite, 'ALTER TABLE users ADD COLUMN x INT'])]
    #[TestWith([SqliteDialect::Sqlite, 'CREATE INDEX i ON users (id)'])]
    public function testResolveFindsTheTableOfEveryStatementKind(Dialect $dialect, string $sql): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(10))');
        $references = Resolved::of($semantics->analyze($sql, [$users]))->references;
        self::assertNotEmpty($references);
        self::assertContainsOnlyInstancesOf(Reference::class, $references);
        self::assertSame(ReferenceKind::Dependency, $references[0]->kind);
        self::assertSame('users', $references[0]->name[count($references[0]->name) - 1]);
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testResolveRejectsATableNoDependencyDeclares(Dialect $dialect): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('No dependency declares the table nope');
        (new Semantics($dialect))->analyze('SELECT 1 FROM nope', []);
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testResolveAppliesDropsAndConditionalDeclarationsInOrder(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)', []);
        $again = $semantics->analyze('CREATE TABLE IF NOT EXISTS users (other INTEGER)', [$users]);
        self::assertSame(ReferenceKind::Dependency, Resolved::of($again)->references[0]->kind);
        self::assertTrue(Resolved::of($again)->references[0]->conditional);
        self::assertSame([], Resolved::of($again)->declarations);
        $drop = $semantics->analyze('DROP TABLE IF EXISTS users', [$users]);
        self::assertSame(ReferenceKind::Drop, Resolved::of($drop)->references[0]->kind);
        self::assertSame($users, Resolved::of($drop)->references[0]->declaration);
        $missing = $semantics->analyze('DROP TABLE IF EXISTS users', [$users, $drop]);
        self::assertNull(Resolved::of($missing)->references[0]->declaration);
        $fresh = $semantics->analyze('CREATE TABLE users (renewed INTEGER)', [$users, $drop]);
        self::assertSame(ReferenceKind::Declaration, Resolved::of($fresh)->references[0]->kind);
        self::assertSame('renewed', Resolved::of($fresh)->declarations[0]->columns[0]->name);
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('No dependency declares the table users');
        $semantics->analyze('SELECT id FROM users', [$users, $drop]);
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testResolveRejectsADuplicateDeclarationAndAnUnconditionalDropOfAnUnknownTable(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        try {
            $semantics->analyze('CREATE TABLE users (id INTEGER)', [$users]);
            self::fail('A duplicate declaration must be rejected.');
        } catch (SemanticException $error) {
            self::assertSame('duplicate-table', $error->reason);
        }
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot drop an unknown table');
        $semantics->analyze('DROP TABLE nope', [$users]);
    }

    public function testResolveReadsAStatementsOwnDeclarationForItsOwnReferences(): void
    {
        $semantics = new Semantics(PostgreSqlDialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent INTEGER REFERENCES users (id))', []);
        $kinds = array_map(static fn (Reference $reference): string => $reference->kind->name, Resolved::of($statement)->references);
        self::assertSame(['Declaration', 'Declaration'], $kinds);
        self::assertCount(1, Resolved::of($statement)->declarations);
    }

    #[TestWith([MySqlDialect::MySql, 'CREATE TABLE copy LIKE users'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'CREATE TABLE copy AS SELECT id FROM users'])]
    #[TestWith([SqliteDialect::Sqlite, 'CREATE TABLE copy AS SELECT id FROM users'])]
    public function testResolveDeclaresATableWhoseColumnsComeFromAnotherRelationWithoutATable(Dialect $dialect, string $sql): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $copy = $semantics->analyze($sql, [$users]);
        self::assertSame(ReferenceKind::Declaration, Resolved::of($copy)->references[0]->kind);
        self::assertNull(Resolved::of($copy)->references[0]->table);
        self::assertSame([], Resolved::of($copy)->declarations);
        $query = $semantics->analyze('SELECT id FROM copy', [$users, $copy]);
        self::assertSame(ReferenceKind::Dependency, Resolved::of($query)->references[0]->kind);
        self::assertNull(Resolved::of($query)->references[0]->table);
    }

    #[TestWith([MySqlDialect::MySql, 'CREATE VIEW v AS SELECT id FROM users'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'CREATE VIEW v AS SELECT id FROM users'])]
    #[TestWith([SqliteDialect::Sqlite, 'CREATE VIEW v AS SELECT id FROM users'])]
    #[TestWith([MySqlDialect::MySql, 'DROP VIEW v'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'DROP VIEW v'])]
    #[TestWith([SqliteDialect::Sqlite, 'DROP VIEW v'])]
    public function testResolveIgnoresTheNamesOfObjectsThatAreNotTables(Dialect $dialect, string $sql): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $names = array_map(static fn (Reference $reference): string => implode('.', $reference->name), Resolved::of($semantics->analyze($sql, [$users]))->references);
        self::assertNotContains('v', $names);
    }

    public function testResolveResolvesAnUnresolvedDependencyFromItsOwnSql(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        self::assertNull($users->resolution);
        $query = $semantics->analyze('SELECT id FROM users', [$users]);
        self::assertSame($users, Resolved::of($query)->references[0]->declaration);
        self::assertSame('id', Resolved::of($query)->references[0]->table?->columns[0]->name);
    }

    #[TestWith([MySqlDialect::MySql, 'app.users', 'WITH users AS( SELECT 1 ) SELECT * FROM users , shadow'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'app.users', 'WITH users AS( SELECT 1 ) SELECT * FROM users , shadow'])]
    public function testResolveAnswersNamesARewriteRecognizesByIdentity(Dialect $dialect, string $table, string $expected): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE ' . $table . ' (id INTEGER)');
        $query = $semantics->analyze('WITH users AS (SELECT 1) SELECT * FROM users, ' . $table, [$users]);
        $table = Resolved::of($query)->tables()[0]->value;
        $shadow = $semantics->builder()->table('shadow');
        self::assertSame($expected, Writer::render(Traversal::rewrite($query->command, static fn (Element $value): Element => $value === $table ? $shadow : $value)));
    }

    public function testResolveAnswersEveryValueOfANameAFormWritesAsSeparateValues(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $users = $semantics->analyze('CREATE TABLE main.users (id INTEGER)');
        $query = $semantics->analyze('SELECT * FROM users, main.users', [$users]);
        [$plain, $qualified] = Resolved::of($query)->tables();
        self::assertSame([$plain->value], $plain->values);
        self::assertSame(['main', '.users'], array_map(Writer::render(...), $qualified->values));
        self::assertSame($qualified->value, $qualified->values[0]);
        self::assertSame($qualified->values, array_values(array_filter(Traversal::find($query->command, Element::class), static fn (Element $value): bool => in_array($value, $qualified->values, true))));
    }

    #[TestWith([MySqlDialect::MySql, 'app', 'app'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'app', 'app'])]
    #[TestWith([SqliteDialect::Sqlite, 'main', 'main'])]
    public function testResolveReadsAnUnqualifiedNameInTheSearchPath(Dialect $dialect, string $schema, string $expected): void
    {
        $semantics = new Semantics($dialect, searchPath: new SearchPath($schema));
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $query = $semantics->analyze('SELECT * FROM users, ' . $schema . '.users', [$users]);
        self::assertSame([$expected, $expected], array_map(static fn (Reference $reference): ?string => $reference->table?->schema, Resolved::of($query)->tables()));
        self::assertSame([$users, $users], array_map(static fn (Reference $reference): ?\SqlSemantics\Statement\Statement => $reference->declaration, Resolved::of($query)->tables()));
    }

    public function testResolveReadsAnUnqualifiedNameInTheFirstSchemaOfThePathThatHasIt(): void
    {
        $semantics = new Semantics(PostgreSqlDialect::PostgreSql, searchPath: new SearchPath('app', 'public'));
        $public = $semantics->analyze('CREATE TABLE public.users (id INTEGER)');
        $app = $semantics->analyze('CREATE TABLE users (id INTEGER)', [$public]);
        self::assertSame('app', Resolved::of($app)->declarations[0]->schema);
        $query = $semantics->analyze('SELECT * FROM users, public.users', [$public, $app]);
        self::assertSame([$app, $public], array_map(static fn (Reference $reference): ?\SqlSemantics\Statement\Statement => $reference->declaration, Resolved::of($query)->tables()));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testResolveNamesUndeclaredTablesUnderPartialDeclarations(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $query = $semantics->analyze('WITH recent AS (SELECT 1) SELECT * FROM users JOIN audit_log ON 1 = 1 JOIN recent ON 1 = 1', [$users], Declarations::Partial);
        $kinds = array_map(static fn (Reference $reference): string => implode('.', $reference->name) . ':' . $reference->kind->name, Resolved::of($query)->references);
        self::assertSame(['recent:CommonTableExpression', 'users:Dependency', 'audit_log:Undeclared', 'recent:CommonTableExpression'], $kinds);
        self::assertNull(Resolved::of($query)->references[2]->table);
        $drop = $semantics->analyze('DROP TABLE audit_log', [$users], Declarations::Partial);
        self::assertSame(ReferenceKind::Drop, Resolved::of($drop)->references[0]->kind);
        $this->expectException(SemanticException::class);
        $semantics->analyze('SELECT * FROM audit_log', [$users, $drop], Declarations::Partial);
    }

    public function testDeclarePutsANewTableInForceAndRefersToAConditionalDuplicate(): void
    {
        [$resolver, $tree, $command, $relations] = Resolving::of(MySqlDialect::MySql, 'CREATE TABLE users (id INT)');
        $declarations = $resolver->declarations($tree);
        $reference = $resolver->declare(new NameSite($command, ['users'], ReferenceKind::Declaration), $relations, $declarations, $tree);
        self::assertSame(ReferenceKind::Declaration, $reference->kind);
        self::assertSame($declarations[0], $reference->table);
        self::assertSame([$declarations[0], null], $relations->lookup('', 'users'));
        $again = $resolver->declare(new NameSite($command, ['users'], ReferenceKind::Declaration, true), $relations, $declarations, $tree);
        self::assertSame(ReferenceKind::Dependency, $again->kind);
        self::assertTrue($again->conditional);
    }

    public function testReferResolvesDefinitionsDropsAndReferencesAgainstTheTablesInForce(): void
    {
        [$resolver, $tree, $command, $relations] = Resolving::of(MySqlDialect::MySql, 'SELECT 1');
        $relations->declare('', 'users', null, null);
        self::assertSame(ReferenceKind::CommonTableExpression, $resolver->refer(new NameSite($command, ['recent'], ReferenceKind::CommonTableExpression), $relations, $tree)->kind);
        self::assertSame(ReferenceKind::CommonTableExpression, $resolver->refer(new NameSite($command, ['recent'], ReferenceKind::Dependency, false, new Scope(['recent'])), $relations, $tree)->kind);
        self::assertSame(ReferenceKind::Declaration, $resolver->refer(new NameSite($command, ['users'], ReferenceKind::Dependency, false, new Scope(['recent'])), $relations, $tree)->kind);
        self::assertSame(ReferenceKind::Declaration, $resolver->refer(new NameSite($command, ['users'], ReferenceKind::Dependency), $relations, $tree)->kind);
        self::assertSame(ReferenceKind::Drop, $resolver->refer(new NameSite($command, ['users'], ReferenceKind::Drop), $relations, $tree)->kind);
        self::assertTrue($resolver->refer(new NameSite($command, ['nope'], ReferenceKind::Drop, true), $relations, $tree)->conditional);
        $this->expectException(SemanticException::class);
        $resolver->refer(new NameSite($command, ['nope'], ReferenceKind::Dependency), $relations, $tree);
    }

    public function testDeclarationsReadsTheReadableTablesOfTheTree(): void
    {
        [$resolver, $tree] = Resolving::of(MySqlDialect::MySql, 'CREATE TABLE users (id INT, name VARCHAR(10))');
        self::assertSame(['users'], array_column($resolver->declarations($tree), 'name'));
        [$resolver, $tree] = Resolving::of(MySqlDialect::MySql, 'CREATE TABLE copy LIKE users');
        self::assertSame([], $resolver->declarations($tree));
    }

    public function testDeclaredFindsATableByItsQualifiedName(): void
    {
        [$resolver, $tree, , $relations] = Resolving::of(MySqlDialect::MySql, 'CREATE TABLE app.users (id INT)');
        $declarations = $resolver->declarations($tree);
        self::assertSame($declarations[0], $resolver->declared($declarations, $relations, 'app', 'users'));
        self::assertNull($resolver->declared($declarations, $relations, '', 'users'));
    }
}
