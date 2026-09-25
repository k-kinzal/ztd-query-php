<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Reference\CursorPosition;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Scalar\Reference\SliceAccess;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Scalar\Reference\VariableAssignment;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\ReferenceExpressions;

#[CoversClass(ReferenceExpressions::class)]
#[Medium]
final class ReferenceExpressionsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t(id INT, a INT[], r JSONB)', 'SELECT a[1], a[1:2], a[:2], a[1:], (r).f, $1 FROM t', 'SELECT "a"[1], "a"[1 : 2], "a"[: 2], "a"[1 :], ("r")."f", $1 FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT t.id, id FROM t', 'SELECT "t"."id" AS "id", "id" AS "id" FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT t.* FROM missing', 'SELECT "t".* FROM "public"."missing"'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT missing FROM t', 'SELECT "missing" AS "missing" FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'UPDATE t SET id = 1 WHERE CURRENT OF c', 'UPDATE "public"."t" SET "id" = 1 WHERE CURRENT OF "c"'])]
    #[TestWith([Dialect::MySql, 'CREATE TABLE t(id INT)', 'SELECT @x := 1, @x, @@SESSION.sql_mode, @@GLOBAL.sql_mode', 'SELECT (@`x` := 1), @`x`, @@SESSION.`sql_mode`, @@GLOBAL.`sql_mode`'])]
    #[TestWith([Dialect::MySql, 'CREATE TABLE t(doc JSON)', "SELECT doc->'$.a', t.doc->>'$.b' FROM t", "SELECT (`doc` -> '$.a'), (`t`.`doc` ->> '$.b') FROM `t`"])]
    #[TestWith([Dialect::Sqlite, 'CREATE TABLE t(id INT)', 'SELECT id FROM t WHERE id = ?1', 'SELECT "id" AS "id" FROM "main"."t" WHERE ("id" = ?1)'])]
    public function testWriteQuotesEveryAccessPathForItsDialect(Dialect $dialect, string $ddl, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build($ddl));
        $statement = $binder->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
    }

    public function testWriteKeepsSliceBoundsAndParameterNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT[])')))->bind('SELECT a[:2], $1 FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $slice = $statement->outputs[0]->expression;
        self::assertInstanceOf(SliceAccess::class, $slice);
        self::assertNull($slice->lower);
        self::assertSame('"a"[: 2]', ReferenceExpressions::write($slice)->toString());
        $parameter = $statement->outputs[1]->expression;
        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame('$1', ReferenceExpressions::write($parameter)->toString());
    }

    public function testWriteQualifiesAWildcardAndAnUnresolvedColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $wildcard = $binder->bind('SELECT t.* FROM missing', strict: false);
        self::assertInstanceOf(BoundSelect::class, $wildcard);
        $star = $wildcard->outputs[0]->expression;
        self::assertInstanceOf(Wildcard::class, $star);
        self::assertSame('"t".*', ReferenceExpressions::write($star)->toString());
        $unresolved = $binder->bind('SELECT missing FROM t', strict: false);
        self::assertInstanceOf(BoundSelect::class, $unresolved);
        $column = $unresolved->outputs[0]->expression;
        self::assertInstanceOf(UnresolvedColumnReference::class, $column);
        self::assertSame('"missing"', ReferenceExpressions::write($column)->toString());
    }

    public function testWriteSpellsACursorPositionAndAVariableAssignment(): void
    {
        $update = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('UPDATE t SET id = 1 WHERE CURRENT OF c');
        self::assertInstanceOf(UpdateTableStatement::class, $update);
        $cursor = $update->where;
        self::assertInstanceOf(CursorPosition::class, $cursor);
        self::assertSame('CURRENT OF "c"', ReferenceExpressions::write($cursor)->toString());
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT @x := 1', strict: false);
        self::assertInstanceOf(BoundSelect::class, $select);
        $assignment = $select->outputs[0]->expression;
        self::assertInstanceOf(VariableAssignment::class, $assignment);
        self::assertSame('(@`x` := 1)', ReferenceExpressions::write($assignment)->toString());
    }

    #[TestWith([VariableScope::User, Dialect::MySql, '@`x`'])]
    #[TestWith([VariableScope::Session, Dialect::MySql, '@@SESSION.`x`'])]
    #[TestWith([VariableScope::Global, Dialect::MySql, '@@GLOBAL.`x`'])]
    #[TestWith([VariableScope::Local, Dialect::MySql, '@@SESSION.`x`'])]
    #[TestWith([VariableScope::User, Dialect::PostgreSql, '@"x"'])]
    public function testVariablePrefixesTheQuotedNameWithItsScope(VariableScope $scope, Dialect $dialect, string $expected): void
    {
        self::assertSame($expected, ReferenceExpressions::variable('x', $scope, $dialect)->toString());
    }

    public function testVariableQuotesEmbeddedQuoteCharacters(): void
    {
        self::assertSame('@`a``b`', ReferenceExpressions::variable('a`b', VariableScope::User, Dialect::MySql)->toString());
    }

    #[TestWith(['SELECT a[1] FROM t', 'SELECT "a"[1] FROM "public"."t"'])]
    #[TestWith(['SELECT (ARRAY[1, 2])[1]', 'SELECT (ARRAY[1, 2])[1]'])]
    #[TestWith(["SELECT ('{1}'::int[])[1:1]", 'SELECT (CAST(\'{1}\' AS integer []))[1 : 1]'])]
    public function testSubscriptedParenthesizesEveryBaseButAPath(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INTEGER[])'));
        $query = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        self::assertInstanceOf(BoundSelect::class, $query);
        $base = $query->outputs[0]->expression->inputs()[0];
        self::assertSame(str_contains($expected, '"a"'), !str_starts_with(ReferenceExpressions::subscripted($base)->toString(), '('));
    }

    public function testWriteParenthesizesAnExpandedComposite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT $1.*');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expansion = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Composite\RowExpansion::class, $expansion);
        self::assertSame('($1).*', ReferenceExpressions::write($expansion)->toString());
    }
}
