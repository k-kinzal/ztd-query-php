<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturningLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ReturningLowering::class)]
#[Medium]
final class ReturningLoweringTest extends TestCase
{
    public function testLowerExpandsTheQualifiedTargetWildcardInDeclarationOrder(): void
    {
        $inspector = new Inspection();
        $table = $inspector->inspect('CREATE TABLE public.t (id INTEGER, balance INTEGER)');
        $operation = $inspector->inspect('DELETE FROM public.t AS t RETURNING t.balance AS b, t.*', [$table]);
        self::assertInstanceOf(Delete::class, $operation->statement);
        $bound = $operation->binding();
        $source = new SourceBinding($bound, $operation->statement->target);
        $fields = (new ReturningLowering())->lower($bound->facts->output, $source);

        self::assertSame(['b', 'id', 'balance'], array_map(static fn ($field): string => $field->name->value, $fields));
        self::assertSame([0, 1, 0], array_map(static fn ($field): int => $field->reference->slot, $fields));
    }
}
