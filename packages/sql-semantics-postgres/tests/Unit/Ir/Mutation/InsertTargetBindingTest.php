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
        $declaration = $inspection->inspect('CREATE TABLE app.events (id INT, amount INT)');
        $operation = $inspection->inspect('INSERT INTO app.events (amount, id) SELECT 2 AS x, 3 AS y', [$declaration]);
        self::assertInstanceOf(ParsedInsertQuery::class, $operation->statement);
        $bound = (new InsertTargetBinding())->lower($operation->binding(), $operation->statement);

        self::assertSame(['amount', 'id'], array_map(static fn ($column): string => $column->value, $bound->columns));
    }
}
