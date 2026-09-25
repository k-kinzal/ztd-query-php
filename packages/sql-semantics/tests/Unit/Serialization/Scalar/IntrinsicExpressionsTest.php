<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\IntrinsicExpressions;

#[CoversClass(IntrinsicExpressions::class)]
#[Medium]
final class IntrinsicExpressionsTest extends TestCase
{
    public function testWriteRoutesAnIntervalOperationAndLeavesLiteralSerializationToItsOwner(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY, 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertNotNull(IntrinsicExpressions::write($query->outputs[0]->expression));
        self::assertNull(IntrinsicExpressions::write($query->outputs[1]->expression));
        self::assertStringContainsString('DATE_ADD(', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteRoutesEveryIntrinsicForm')]
    public function testWriteRoutesEveryIntrinsicForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, IntrinsicExpressions::write($statement->outputs[0]->expression)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteRoutesEveryIntrinsicForm(): iterable
    {
        return [
            'SELECT POSITION(\'a\' IN \'abc\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT POSITION(\'a\' IN \'abc\')', 'POSITION(\'a\' IN \'abc\')'],
            'SELECT TRIM(BOTH \'x\' FROM \'xax\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT TRIM(BOTH \'x\' FROM \'xax\')', 'TRIM(BOTH \'x\' FROM \'xax\')'],
            'SELECT NORMALIZE(\'a\', NFC) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT NORMALIZE(\'a\', NFC)', 'NORMALIZE(\'a\', NFC)'],
            'SELECT \'a\' IS NFC NORMALIZED (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT \'a\' IS NFC NORMALIZED', '((\'a\') IS NFC NORMALIZED)'],
            'SELECT MATCH (a) AGAINST (\'x\') FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT MATCH (a) AGAINST (\'x\') FROM t', 'MATCH(`a`) AGAINST(\'x\' IN NATURAL LANGUAGE MODE)'],
            'SELECT DATE_ADD(d, INTERVAL 1 DAY) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT DATE_ADD(d, INTERVAL 1 DAY) FROM t', 'DATE_ADD(`d`, INTERVAL 1 DAY)'],
            'SELECT EXTRACT(YEAR FROM DATE \'2020-01-01\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT EXTRACT(YEAR FROM DATE \'2020-01-01\')', 'EXTRACT(YEAR FROM CAST(\'2020-01-01\' AS date))'],
            'SELECT (DATE \'2020-01-01\', DATE \'2020-02-01\') OVERLAPS (DATE \'2020-01-15\', DATE \'2020-03-01\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT (DATE \'2020-01-01\', DATE \'2020-02-01\') OVERLAPS (DATE \'2020-01-15\', DATE \'2020-03-01\')', '((CAST(\'2020-01-01\' AS date), CAST(\'2020-02-01\' AS date)) OVERLAPS(CAST(\'2020-01-15\' AS date), CAST(\'2020-03-01\' AS date)))'],
            'SELECT TIMESTAMP \'2020-01-01 00:00\' AT TIME ZONE \'UTC\' (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT TIMESTAMP \'2020-01-01 00:00\' AT TIME ZONE \'UTC\'', '(CAST(\'2020-01-01 00:00\' AS timestamp) AT TIME ZONE \'UTC\')'],
            'SELECT GET_FORMAT(DATE, \'USA\') (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT GET_FORMAT(DATE, \'USA\')', 'GET_FORMAT(DATE, \'USA\')'],
            'SELECT DATE_SUB(d, INTERVAL 1 DAY) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT DATE_SUB(d, INTERVAL 1 DAY) FROM t', 'DATE_SUB(`d`, INTERVAL 1 DAY)'],
            'SELECT EXTRACT(YEAR FROM d) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT EXTRACT(YEAR FROM d) FROM t', 'EXTRACT(YEAR FROM `d`)'],
            'SELECT RAISE(IGNORE) (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT RAISE(IGNORE)', 'RAISE(IGNORE)'],
            'SELECT RAISE(ABORT, \'x\') (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT RAISE(ABORT, \'x\')', 'RAISE(ABORT, \'x\')'],
            'SELECT JSON_EXISTS(\'{}\', \'$.a\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT JSON_EXISTS(\'{}\', \'$.a\')', 'JSON_EXISTS(\'{}\', \'$.a\')'],
            'SELECT XMLELEMENT(NAME a) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT XMLELEMENT(NAME a)', 'XMLELEMENT(NAME "a")'],
        ];
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteRoutesDocumentAndFormatForms')]
    public function testWriteRoutesDocumentAndFormatForms(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, IntrinsicExpressions::write($statement->outputs[0]->expression)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteRoutesDocumentAndFormatForms(): iterable
    {
        return [
            'SELECT JSON_VALUE(j, \'$.a\') FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a TEXT, j JSON, d DATE)'], 'SELECT JSON_VALUE(j, \'$.a\') FROM t', 'JSON_VALUE(`j`, \'$.a\')'],
        ];
    }
}
