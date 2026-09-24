<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor;
use SqlSemantics\Model\Scalar\Document\Construction\JsonMember;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor;
use SqlSemantics\Model\Scalar\Document\JsonExistence;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\SqlJsonExpressions;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(SqlJsonExpressions::class)]
#[Medium]
final class SqlJsonExpressionsTest extends TestCase
{
    public function testWriteSpellsEachFunctionAndLeavesOthers(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $path = Expression::literal('$', Dialect::PostgreSql);
        self::assertSame("JSON_EXISTS('{}', '$' FALSE ON ERROR)", SqlJsonExpressions::write(new JsonExistence($document->source, new Input($document), $path, [], ExistsResponse::False))?->toString());
        self::assertSame("JSON_ARRAY('{}' FORMAT JSON NULL ON NULL)", SqlJsonExpressions::write(new JsonArrayConstructor($document->source, [new Input($document, Format::Json)], JsonNullHandling::Null))?->toString());
        self::assertSame('JSON_OBJECT()', SqlJsonExpressions::write(new JsonObjectConstructor($document->source, []))?->toString());
        self::assertSame("JSON_ARRAYAGG('{}' ORDER BY '$' DESC)", SqlJsonExpressions::write(new JsonArrayAggregate($document->source, new Input($document), [new \SqlSemantics\Model\Ordering($path, true)]))?->toString());
        self::assertNull(SqlJsonExpressions::write($document));
    }

    public function testCompositeWritesOnlyConstructorsAndAggregates(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame("JSON_ARRAY('{}')", SqlJsonExpressions::composite(new JsonArrayConstructor($document->source, [new Input($document)]))?->toString());
        self::assertNull(SqlJsonExpressions::composite(new JsonExistence($document->source, new Input($document), $document)));
    }

    public function testCallParenthesizesTheClauses(): void
    {
        self::assertSame('JSON_SCALAR(1)', SqlJsonExpressions::call('JSON_SCALAR', [Build::keyword('1')])->toString());
    }

    public function testDocumentWritesTheFormatPathAndPassing(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $path = Expression::literal('$.a', Dialect::PostgreSql);
        self::assertSame("'{}' FORMAT JSON, '$.a' PASSING 1 AS \"x\"", SqlJsonExpressions::document(new Input($document, Format::Json), $path, [new PassingArgument('x', new Input(Expression::literal(1, Dialect::PostgreSql)))])->toString());
    }

    public function testPassingWritesNothingWithoutVariables(): void
    {
        self::assertSame('', SqlJsonExpressions::passing([])->toString());
    }

    #[TestWith(['json', null, 'RETURNING json'])]
    #[TestWith(['bytea', Format::Utf8, 'RETURNING bytea FORMAT JSON ENCODING UTF8'])]
    public function testReturningWritesTheTypeAndFormat(string $type, ?Format $format, string $expected): void
    {
        self::assertSame($expected, SqlJsonExpressions::returning(new JsonReturning(TypeDescriptor::builtin(Dialect::PostgreSql, $type), $format))->toString());
        self::assertSame('', SqlJsonExpressions::returning(null)->toString());
    }

    public function testMemberUsesTheColonSpelling(): void
    {
        self::assertSame("'a' : 1", SqlJsonExpressions::member(new JsonMember(Expression::literal('a', Dialect::PostgreSql), new Input(Expression::literal(1, Dialect::PostgreSql))))->toString());
    }

    public function testObjectOptionsWriteOnlyNonDefaults(): void
    {
        self::assertSame([], SqlJsonExpressions::objectOptions(JsonNullHandling::Null, false));
        self::assertSame(['ABSENT ON NULL', 'WITH UNIQUE KEYS'], array_map(static fn ($tree): string => $tree->toString(), SqlJsonExpressions::objectOptions(JsonNullHandling::Absent, true)));
    }

    public function testFilteredAppendsTheCondition(): void
    {
        $call = Build::keyword('JSON_ARRAYAGG(1)');
        self::assertSame($call, SqlJsonExpressions::filtered($call, null));
        self::assertSame('JSON_ARRAYAGG(1) FILTER (WHERE TRUE)', SqlJsonExpressions::filtered($call, Expression::literal(true, Dialect::PostgreSql))->toString());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerBoundFunctions(): array
    {
        return [
            ['JSON_QUERY(jsonb \'{}\', \'$.a\' PASSING 1 AS x, 2 AS y RETURNING jsonb WITH CONDITIONAL WRAPPER KEEP QUOTES NULL ON EMPTY ERROR ON ERROR)', 'JSON_QUERY(CAST(\'{}\' AS jsonb), \'$.a\' PASSING 1 AS "x", 2 AS "y" RETURNING jsonb WITH CONDITIONAL WRAPPER KEEP QUOTES NULL ON EMPTY ERROR ON ERROR)'],
            ['JSON_QUERY(jsonb \'{}\', \'$.a\' OMIT QUOTES)', 'JSON_QUERY(CAST(\'{}\' AS jsonb), \'$.a\' OMIT QUOTES)'],
            ['JSON_QUERY(jsonb \'{}\', \'$.a\')', 'JSON_QUERY(CAST(\'{}\' AS jsonb), \'$.a\')'],
            ['JSON_EXISTS(jsonb \'{}\', \'$.a\' TRUE ON ERROR)', 'JSON_EXISTS(CAST(\'{}\' AS jsonb), \'$.a\' TRUE ON ERROR)'],
            ['JSON_EXISTS(jsonb \'{}\', \'$.a\')', 'JSON_EXISTS(CAST(\'{}\' AS jsonb), \'$.a\')'],
            ['JSON_SERIALIZE(jsonb \'{}\' RETURNING bytea)', 'JSON_SERIALIZE(CAST(\'{}\' AS jsonb) RETURNING bytea)'],
            ['JSON(\'{}\' WITH UNIQUE KEYS)', 'JSON(\'{}\' WITH UNIQUE KEYS)'],
            ['JSON(\'{}\')', 'JSON(\'{}\')'],
            ['JSON_SCALAR(1)', 'JSON_SCALAR(1)'],
            ['JSON_OBJECT(\'a\' : 1, \'b\' : 2 ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb)', 'JSON_OBJECT(\'a\' : 1, \'b\' : 2 ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb)'],
            ['JSON_OBJECT(RETURNING jsonb)', 'JSON_OBJECT(RETURNING jsonb)'],
            ['JSON_ARRAY(1, 2 NULL ON NULL RETURNING jsonb)', 'JSON_ARRAY(1, 2 NULL ON NULL RETURNING jsonb)'],
            ['JSON_ARRAY(1, 2)', 'JSON_ARRAY(1, 2)'],
            ['JSON_ARRAY()', 'JSON_ARRAY()'],
            ['JSON_ARRAY(SELECT 1 FORMAT JSON RETURNING jsonb)', 'JSON_ARRAY(SELECT 1 FORMAT JSON RETURNING jsonb)'],
            ['JSON_ARRAY(SELECT 1)', 'JSON_ARRAY(SELECT 1)'],
            ['JSON_OBJECTAGG(k : v ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb) FILTER (WHERE v > 0) FROM t', 'JSON_OBJECTAGG("k" : "v" ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb) FILTER (WHERE ("v" > 0))'],
            ['JSON_OBJECTAGG(k : v) FROM t', 'JSON_OBJECTAGG("k" : "v")'],
            ['JSON_ARRAYAGG(v ORDER BY v NULL ON NULL RETURNING jsonb) FILTER (WHERE v > 0) FROM t', 'JSON_ARRAYAGG("v" ORDER BY "v" ASC NULL ON NULL RETURNING jsonb) FILTER (WHERE ("v" > 0))'],
            ['JSON_ARRAYAGG(v) FROM t', 'JSON_ARRAYAGG("v")'],
        ];
    }

    #[DataProvider('providerBoundFunctions')]
    public function testWriteWritesEachBoundFunction(string $expression, string $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k TEXT, v INT)')))->bind('SELECT ' . $expression);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, SqlJsonExpressions::write($query->outputs[0]->expression)?->toString());
    }
}
