<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(JsonArrayQuery::class)]
#[Medium]
final class JsonArrayQueryTest extends TestCase
{
    public function testOutputNameIsJsonArray(): void
    {
        $operation = (new Inspection())->diagnose('SELECT JSON_ARRAY(SELECT 1, 2 RETURNING text)', []);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $target = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $node = $target->expression;
        self::assertInstanceOf(JsonArrayQuery::class, $node);
        self::assertSame('json_array', $node->outputName()->value);
    }

    public function testDeriveScalarReportsAQueryOfSeveralColumns(): void
    {
        $operation = (new Inspection())->diagnose('SELECT JSON_ARRAY(SELECT 1, 2 RETURNING text)', []);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $target = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $node = $target->expression;
        self::assertInstanceOf(JsonArrayQuery::class, $node);
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $operation->facts->scalar($node));
        self::assertEquals([new JsonProblem(JsonProblemKind::SubqueryColumns)], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheQueryWithoutParentheses(): void
    {
        $operation = (new Inspection())->diagnose('SELECT JSON_ARRAY(SELECT 1, 2 RETURNING text)', []);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $target = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $node = $target->expression;
        self::assertInstanceOf(JsonArrayQuery::class, $node);
        self::assertSame('SELECT JSON_ARRAY(SELECT 1, 2 RETURNING text)', $operation->toString());
    }
}
