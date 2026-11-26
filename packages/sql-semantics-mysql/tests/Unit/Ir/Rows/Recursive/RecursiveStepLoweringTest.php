<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Recursive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Rows\Recursive\RecursiveStepLowering;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RecursiveStepLowering::class)]
#[Medium]
final class RecursiveStepLoweringTest extends TestCase
{
    public function testLowerKeepsTheContinuationAndSignedStep(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        self::assertInstanceOf(With::class, $root->with);
        $definition = $root->with->tables[0];
        $set = $definition->query;
        self::assertInstanceOf(SetOperation::class, $set);
        self::assertInstanceOf(Select::class, $set->right);

        [$increment, $boundary, $comparison] = (new RecursiveStepLowering())->lower($query->binding(), $set->right, $definition);
        self::assertSame(['2', '6', '<'], [$increment->decimal, $boundary->decimal, $comparison->value]);
    }
}
