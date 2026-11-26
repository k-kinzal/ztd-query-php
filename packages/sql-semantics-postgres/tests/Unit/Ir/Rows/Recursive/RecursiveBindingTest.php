<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Recursive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive\RecursiveBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(RecursiveBinding::class)]
#[Medium]
final class RecursiveBindingTest extends TestCase
{
    public function testSourceReturnsTheActualWorkingOccurrence(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        self::assertInstanceOf(Select::class, $root->body);
        self::assertNotNull($root->with);
        $binding = new RecursiveBinding();

        self::assertSame($root->body->from, $binding->source($query->binding(), $root->body, $root->with->tables[0]));
    }

    public function testReadAcceptsAReferenceBoundToTheWorkingSlot(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        self::assertInstanceOf(Select::class, $root->body);
        self::assertInstanceOf(TableInput::class, $root->body->from);
        $field = $root->body->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $field);

        (new RecursiveBinding())->read($query->binding(), $field->expression, $root->body->from);
    }
}
