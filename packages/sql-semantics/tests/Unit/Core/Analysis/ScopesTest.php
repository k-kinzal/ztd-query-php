<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Forms;
use SqlSemantics\Core\Analysis\Scope;
use SqlSemantics\Core\Analysis\Scopes;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Policy\WithVisibility;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Resolving;

#[CoversClass(Scopes::class)]
#[UsesClass(Scope::class)]
#[UsesClass(WithVisibility::class)]
#[UsesClass(Forms::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Resolver::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\NameSites::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[Medium]
final class ScopesTest extends TestCase
{
    public function testWalkYieldsEveryValueInWalkingOrderWithTheScopeVisibleAtIt(): void
    {
        $command = (new Semantics(MySqlDialect::MySql))->analyze('WITH a AS (SELECT 1 FROM t), b AS (SELECT 1 FROM u) SELECT 1 FROM v')->command;
        $walked = Resolving::walked(MySqlDialect::MySql, $command);
        self::assertSame(iterator_to_array(Traversal::walk($command), false), array_column($walked, 0));
        self::assertSame(['t' => [], 'u' => ['a'], 'v' => ['a', 'b']], Resolving::visibleAt($walked, ['t', 'u', 'v']));
    }

    public function testExpressionsGivesTheBodyOfEachExpressionTheNamesItCanSee(): void
    {
        $command = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('WITH a AS (SELECT 1), b AS (SELECT 1) SELECT 1')->command;
        $clause = Resolving::form(PostgreSqlDialect::PostgreSql, $command, 'with_clause');
        $scopes = Resolving::scopes(PostgreSqlDialect::PostgreSql);
        self::assertSame([['outer'], ['outer', 'a']], array_values(array_map(static fn (Scope $scope): array => $scope->names, $scopes->expressions($clause, new Scope(['outer']), WithVisibility::Preceding))));
        self::assertSame([['a', 'b'], ['a', 'b']], array_values(array_map(static fn (Scope $scope): array => $scope->names, $scopes->expressions($clause, new Scope(), WithVisibility::All))));
    }

    public function testClauseWalksEachExpressionWithItsOwnScope(): void
    {
        $command = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('WITH a AS (SELECT 1 FROM t), b AS (SELECT 1 FROM u) SELECT 1')->command;
        $clause = Resolving::form(PostgreSqlDialect::PostgreSql, $command, 'with_clause');
        $expressions = Resolving::scopes(PostgreSqlDialect::PostgreSql)->expressions($clause, new Scope(['outer']), WithVisibility::Preceding);
        $walked = Resolving::pairs(Resolving::scopes(PostgreSqlDialect::PostgreSql)->clause($clause, new Scope(['outer']), $expressions));
        self::assertSame($clause, $walked[0][0]);
        self::assertSame(['outer'], $walked[0][1]->names);
        self::assertSame(['t' => ['outer'], 'u' => ['outer', 'a']], Resolving::visibleAt($walked, ['t', 'u']));
    }

    public function testChildrenGivesATargetNoScopeAndTheClauseTheOuterScope(): void
    {
        $scopes = Resolving::scopes(PostgreSqlDialect::PostgreSql);
        $command = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('WITH a AS (SELECT 1) UPDATE t SET x = 1 FROM a')->command;
        $update = Resolving::form(PostgreSqlDialect::PostgreSql, $command, 'UpdateStmt');
        $children = $scopes->children($update, new Scope(['outer']));
        self::assertSame(['outer'], $children[0]->names);
        self::assertSame([], $children[1]->names);
        self::assertSame(['outer', 'a'], $children[3]->names);
        self::assertSame([], $scopes->children($update->children()[5], new Scope()));
    }

    public function testWithClauseFindsTheChildIndexOfTheClauseAFormWrites(): void
    {
        $scopes = Resolving::scopes(PostgreSqlDialect::PostgreSql);
        $update = Resolving::form(PostgreSqlDialect::PostgreSql, (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('WITH a AS (SELECT 1) UPDATE t SET x = 1')->command, 'UpdateStmt');
        self::assertSame(0, $scopes->withClause($update));
        self::assertNull($scopes->withClause($update->children()[1]));
    }

    public function testVisibilityReadsTheRecursiveWordOfTheClauseOrItsForm(): void
    {
        $sqlite = Resolving::scopes(SqliteDialect::Sqlite);
        $select = Resolving::form(SqliteDialect::Sqlite, (new Semantics(SqliteDialect::Sqlite))->analyze('WITH RECURSIVE a AS (SELECT 1) SELECT * FROM a')->command, 'select');
        self::assertSame(WithVisibility::All, $sqlite->visibility($select, $select->children()[0]));
        $mysql = Resolving::scopes(MySqlDialect::MySql);
        $plain = (new Semantics(MySqlDialect::MySql))->analyze('WITH a AS (SELECT 1) SELECT * FROM a')->command;
        $recursive = (new Semantics(MySqlDialect::MySql))->analyze('WITH RECURSIVE a AS (SELECT 1) SELECT * FROM a')->command;
        $form = Resolving::form(MySqlDialect::MySql, $plain, 'query_expression');
        self::assertSame(WithVisibility::Preceding, $mysql->visibility($form, $form->children()[0]));
        $form = Resolving::form(MySqlDialect::MySql, $recursive, 'query_expression');
        self::assertSame(WithVisibility::PrecedingAndItself, $mysql->visibility($form, $form->children()[0]));
    }

    public function testDefinitionsListTheNamesOfOneClauseWithoutTheClausesInsideTheirBodies(): void
    {
        $scopes = Resolving::scopes(MySqlDialect::MySql);
        $command = (new Semantics(MySqlDialect::MySql))->analyze('WITH a AS (WITH inner_one AS (SELECT 1) SELECT 1), `B` AS (SELECT 1) SELECT 1')->command;
        $clause = Resolving::form(MySqlDialect::MySql, $command, 'with_clause');
        self::assertSame(['a', 'B'], array_column($scopes->definitions($clause), 1));
    }

    public function testDefinitionDecodesTheNameOfAnExpressionOnly(): void
    {
        $scopes = Resolving::scopes(MySqlDialect::MySql);
        $command = (new Semantics(MySqlDialect::MySql))->analyze('WITH `a` AS (SELECT 1) SELECT 1')->command;
        self::assertNull($scopes->definition(Resolving::form(MySqlDialect::MySql, $command, 'with_clause')));
        self::assertSame('a', $scopes->definition(Resolving::form(MySqlDialect::MySql, $command, 'common_table_expr')));
    }

    public function testPositionsNamesTheSymbolOfEachChild(): void
    {
        self::assertSame(['ident', 'ident'], Resolving::scopes(MySqlDialect::MySql)->positions(['ident', '.', 'ident']));
        self::assertSame([], Resolving::scopes(MySqlDialect::MySql)->positions([]));
    }
}
