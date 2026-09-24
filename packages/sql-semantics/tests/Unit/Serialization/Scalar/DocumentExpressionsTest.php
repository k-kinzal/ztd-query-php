<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Serialization\Scalar\DocumentExpressions;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DocumentExpressions::class)]
#[Medium]
final class DocumentExpressionsTest extends TestCase
{
    public function testWriteSpellsReturningAndOnlyNonDefaultResponses(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $document = Expression::literal('{}', Dialect::MySql);
        self::assertSame("JSON_VALUE('{}', '$.a')", DocumentExpressions::write(new JsonScalarExtraction($path->source, $document, $path))->toString());
        $value = new JsonScalarExtraction($path->source, $document, $path, TypeDescriptor::builtin(Dialect::MySql, 'date'), ValueBehavior::Default, new DefaultResponse(Expression::literal('x', Dialect::MySql)));
        self::assertSame("JSON_VALUE('{}', '$.a' RETURNING DATE DEFAULT 'x' ON ERROR)", DocumentExpressions::write($value)->toString());
    }

    public function testWriteSpellsThePostgreSqlFormatAndPassing(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $path = Expression::literal('$.a', Dialect::PostgreSql);
        $value = new JsonScalarExtraction($path->source, $document, $path, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ValueBehavior::Null, ValueBehavior::Error, \SqlSemantics\Model\TableFunction\Json\Format::Json, [new \SqlSemantics\Model\TableFunction\Json\PassingArgument('x', new \SqlSemantics\Model\TableFunction\Json\Input($path))]);
        self::assertSame("JSON_VALUE('{}' FORMAT JSON, '$.a' PASSING '$.a' AS \"x\" RETURNING integer NULL ON EMPTY ERROR ON ERROR)", DocumentExpressions::write($value)->toString());
    }
}
