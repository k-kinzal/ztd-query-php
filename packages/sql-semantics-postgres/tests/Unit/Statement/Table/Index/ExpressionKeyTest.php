<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey::class)]
#[Medium]
final class ExpressionKeyTest extends TestCase
{
    public function testDeriveScalarAnswersTheTypeOfTheExpression(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE INDEX ON t ((a + 1))', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        $n2 = $n1->elements[0]->key;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey::class, $n2);
        $n3 = $statement->facts->scalar($n2)->type;
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $n3);
        self::assertSame('integer', $n3->descriptor->name());
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE INDEX ON t ((a + 1), lower(c), ((b)))', []);
        self::assertSame('CREATE INDEX ON t ((a + 1), lower(c), ((b)))', $statement->toString());
    }

    public function testRefusesAnOperatorWithoutParentheses(): void
    {
        $this->expectExceptionMessage('A key expression other than a function call is written between parentheses.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('a')]), false);
    }
}
