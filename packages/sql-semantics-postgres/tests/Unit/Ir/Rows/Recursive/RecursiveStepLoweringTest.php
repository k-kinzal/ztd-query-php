<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Recursive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive\RecursiveStepLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(RecursiveStepLowering::class)]
#[Medium]
final class RecursiveStepLoweringTest extends TestCase
{
    public function testLowerKeepsTheContinuationAndSignedStep(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        self::assertNotNull($root->with);
        $definition = $root->with->tables[0];
        $set = $definition->query;
        self::assertInstanceOf(SetOperation::class, $set);
        self::assertInstanceOf(Select::class, $set->right);

        [$increment, $boundary, $comparison] = (new RecursiveStepLowering())->lower($query->binding(), $set->right, $definition);
        self::assertSame(['2', '6', '<'], [$increment->decimal, $boundary->decimal, $comparison->value]);
    }

    public function testOperatorReadsTheResolvedBuiltin(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        self::assertNotNull($root->with);
        $set = $root->with->tables[0]->query;
        self::assertInstanceOf(SetOperation::class, $set);
        self::assertInstanceOf(Select::class, $set->right);
        self::assertInstanceOf(BinaryOperation::class, $set->right->where);

        self::assertSame('<', (new RecursiveStepLowering())->operator($set->right->where, true));
    }
}
