<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertTargetBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedInsertQuery;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(InsertTargetBinding::class)]
#[Medium]
final class InsertTargetBindingTest extends TestCase
{
    public function testLowerKeepsExplicitWriteOrder(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE app.events (id INT, amount INT, stamp INT)');
        $operation = $inspection->inspect('INSERT INTO app.events (amount, id) SELECT 2 AS x, 3 AS y RETURNING stamp', [$declaration]);
        self::assertInstanceOf(ParsedInsertQuery::class, $operation->statement);
        [$target, $returning] = (new InsertTargetBinding())->lower($operation->binding(), $operation->statement);

        self::assertSame(['amount', 'id', 'stamp'], array_map(static fn ($column): string => $column->value, $target->relation->columns));
        self::assertCount(2, $target->written);
        self::assertSame(2, $returning[0]->reference->slot);
    }
}
