<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversClass(UpsertExpressionKind::class)]
final class UpsertExpressionKindTest extends TestCase
{
    public function testDefinesEveryExpressionKind(): void
    {
        self::assertSame([
            'Literal',
            'Column',
            'UnaryPlus',
            'UnaryMinus',
            'Not',
            'Add',
            'Subtract',
            'Multiply',
            'Divide',
            'Modulo',
            'Equal',
            'NotEqual',
            'Less',
            'LessOrEqual',
            'Greater',
            'GreaterOrEqual',
            'And',
            'Or',
        ], array_column(UpsertExpressionKind::cases(), 'name'));
    }
}
