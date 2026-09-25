<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Expressions;

#[CoversClass(Expressions::class)]
#[Medium]
final class ExpressionsTest extends TestCase
{
    #[TestWith(['1', '1'])]
    #[TestWith(['CURRENT_TIMESTAMP(3)', 'CURRENT_TIMESTAMP(3)'])]
    #[TestWith(['ROW(1, 2)', 'ROW(1, 2)'])]
    #[TestWith(['t.id', '"t"."id"'])]
    #[TestWith(['$1', '$1'])]
    #[TestWith(['n COLLATE "C"', '("n" COLLATE "C")'])]
    #[TestWith(['id + 1', '("id" + 1)'])]
    #[TestWith(['-id', '(- "id")'])]
    #[TestWith(['CAST(id AS text)', 'CAST("id" AS text)'])]
    #[TestWith(['COALESCE(id, 1)', 'COALESCE("id", 1)'])]
    #[TestWith(['id BETWEEN 1 AND 2', '("id" BETWEEN 1 AND 2)'])]
    #[TestWith(['CASE WHEN id > 1 THEN 2 END', 'CASE WHEN ("id" > 1) THEN 2 END'])]
    #[TestWith(['(SELECT 1)', '(SELECT 1)'])]
    #[TestWith(['EXISTS (SELECT 1)', 'EXISTS(SELECT 1)'])]
    #[TestWith(['id IN (SELECT id FROM s)', '("id" IN (SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['lower(n)', '"lower"("n")'])]
    #[TestWith(['count(*)', '"count"(*)'])]
    #[TestWith(['sum(n) OVER ()', '"sum"("n") OVER ()'])]
    #[TestWith(['EXTRACT(YEAR FROM CURRENT_DATE)', 'EXTRACT(YEAR FROM CURRENT_DATE)'])]
    #[TestWith(['POSITION(\'a\' IN n)', 'POSITION(\'a\' IN "n")'])]
    public function testWriteRoutesEveryExpressionCategoryToItsSerializer(string $expression, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n TEXT)', 'CREATE TABLE s(id INT, n TEXT)'));
        $statement = $binder->bind('SELECT ' . $expression . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, Expressions::write($statement->outputs[0]->expression)->toString());
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($statement->outputs[0]->expression::class, $rebound->outputs[0]->expression::class);
        self::assertSame($expected, Expressions::write($rebound->outputs[0]->expression)->toString());
    }

    public function testWriteSerializesTriggerControlFlow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind("CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); END");
        self::assertSame("CREATE TRIGGER \"tr\" AFTER INSERT ON \"main\".\"t\" FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'no'); END", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['ARRAY(SELECT 1)', 'ARRAY(SELECT 1)'])]
    #[TestWith(['1 = ANY (ARRAY[1])', '(1 = ANY (ARRAY[1]))'])]
    #[TestWith(['coalesce(1, 2)', 'COALESCE(1, 2)'])]
    public function testCompositeWritesExpressionsThatContainExpressions(string $expression, string $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ' . $expression);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, Expressions::composite($query->outputs[0]->expression)->toString());
    }

    /**
     * @return array<string, array{Dialect, string, string, string}>
     */
    public static function providerOutputs(): array
    {
        return [
            'introduced literal' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT _utf8mb4'x'", "_utf8mb4 'x'"],
            'temporal literal' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT DATE '2020-01-01'", "DATE '2020-01-01'"],
            'unresolved column' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT nope FROM t', '"nope"'],
            'wildcard' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT * FROM nope', '"nope".*'],
            'field access' => [Dialect::PostgreSql, 'CREATE TYPE pair AS (a INT, b INT); CREATE TABLE t(id INT, p pair)', 'SELECT (p).a FROM t', '("p")."a"'],
            'element access' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT, arr INT[])', 'SELECT arr[1] FROM t', '"arr"[1]'],
            'slice access' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT, arr INT[])', 'SELECT arr[1:2] FROM t', '"arr"[1 : 2]'],
            'unresolved variable' => [Dialect::MySql, 'CREATE TABLE t(id INT)', 'SELECT @@nope_var', '@@SESSION.`nope_var`'],
            'variable assignment' => [Dialect::MySql, 'CREATE TABLE t(id INT)', 'SELECT @v := 1', '(@`v` := 1)'],
            'json path extraction' => [Dialect::MySql, 'CREATE TABLE t(id INT, j JSON)', "SELECT j->'$.a' FROM t", "(`j` -> '$.a')"],
            'row expansion' => [Dialect::PostgreSql, 'CREATE TYPE pair AS (a INT, b INT); CREATE TABLE t(id INT, p pair)', 'SELECT (p).* FROM t', '("p").*'],
            'array cast' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT CAST('a' AS CHAR ARRAY)", "CAST('a' AS CHAR ARRAY)"],
            'time zone cast' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT CAST(TIMESTAMP '2020-01-01 00:00:00' AT TIME ZONE '+00:00' AS DATETIME)", "CAST(TIMESTAMP '2020-01-01 00:00:00' AT TIME ZONE '+00:00' AS DATETIME)"],
            'character set conversion' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT CONVERT('a' USING utf8mb4)", "CONVERT('a' USING `utf8mb4`)"],
            'qualified infix operator' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT 1 OPERATOR(s.+) 2', '(1 OPERATOR("s".+) 2)'],
            'qualified prefix operator' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT OPERATOR(s.-) 2', '(OPERATOR("s".-) 2)'],
            'json predicate' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', "SELECT '{}' IS JSON", "('{}' IS JSON VALUE)"],
            'json membership' => [Dialect::MySql, 'CREATE TABLE t(id INT)', "SELECT 1 MEMBER OF ('[1]')", "(1 MEMBER OF('[1]'))"],
            'extremum' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT GREATEST(1, 2)', 'GREATEST(1, 2)'],
            'null if' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT NULLIF(1, 2)', 'NULLIF(1, 2)'],
            'in list' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT 1 IN (1, 2)', '(1 IN (1, 2))'],
            'pattern match' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', "SELECT 'a' LIKE 'b'", "('a' LIKE 'b')"],
            'simple case' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT CASE 1 WHEN 1 THEN 2 END', 'CASE 1 WHEN 1 THEN 2 END'],
            'quantified comparison' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT 1 = ANY (SELECT 1)', '(1 = ANY(SELECT 1))'],
            'ordered set call' => [Dialect::PostgreSql, 'CREATE TABLE t(id INT)', 'SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY id) FROM t', '"percentile_cont"(0.5) WITHIN GROUP(ORDER BY "id" ASC)'],
        ];
    }

    #[DataProvider('providerOutputs')]
    public function testWriteWritesEachOutputExpressionKind(Dialect $dialect, string $ddl, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build($ddl)))->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, Expressions::write($statement->outputs[0]->expression)->toString());
    }

    #[TestWith(['SELECT make_interval(days => 1)', 0, '"days" => 1'])]
    #[TestWith(["SELECT concat(VARIADIC ARRAY['a'])", 0, "VARIADIC ARRAY['a']"])]
    #[TestWith(['SELECT (1, 2) = (SELECT 1, 2)', 1, '(SELECT 1, 2)'])]
    public function testWriteWritesNestedOperands(string $sql, int $input, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, Expressions::write($statement->outputs[0]->expression->inputs()[$input])->toString());
    }

    public function testWriteWritesACursorPosition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('DELETE FROM t WHERE CURRENT OF c');
        self::assertInstanceOf(DeleteTableStatement::class, $statement);
        self::assertNotNull($statement->where);
        self::assertSame('CURRENT OF "c"', Expressions::write($statement->where)->toString());
    }

    #[TestWith([Dialect::MySql, 'SET sql_mode = ON', 'ON'])]
    #[TestWith([Dialect::PostgreSql, 'SET search_path = public', '"public"'])]
    public function testWriteWritesConfigurationValues(Dialect $dialect, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(AssignedSetting::class, $setting);
        self::assertSame($expected, Expressions::write($setting->values[0])->toString());
    }

    public function testWriteWritesAMetadataColumnAsItsLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement::class, $statement);
        self::assertSame('`Level`', Expressions::write($statement->resultColumns()[0]->expression)->toString());
    }
}
