<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertLiteral;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversNothing]
final class UpsertLiteralSourceTest extends TestCase
{
    public function testValueFlowsIntoAnExpressionWithoutCoercion(): void
    {
        $value = new stdClass();
        $expression = new UpsertExpression(UpsertExpressionKind::Literal, new UpsertLiteral($value));

        self::assertSame($value, $expression->evaluate([], [], 'users'));
    }
}
