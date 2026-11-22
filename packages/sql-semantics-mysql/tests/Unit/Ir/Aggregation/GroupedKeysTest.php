<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedKeys;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GroupedKeys::class)]
#[Medium]
final class GroupedKeysTest extends TestCase
{
    public function testLowerPreservesTheGroupingSlotSequence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT, value INT)');
        $operation = $inspection->inspect('SELECT count(*) FROM app.events GROUP BY value, id, value', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $keys = (new GroupedKeys())->lower($operation->statement, $binding);

        self::assertSame([0, 1, 0], array_map(static fn ($key): int => $key->slot, $keys));
    }
}
