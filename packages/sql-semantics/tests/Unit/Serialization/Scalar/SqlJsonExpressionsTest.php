<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
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
}
