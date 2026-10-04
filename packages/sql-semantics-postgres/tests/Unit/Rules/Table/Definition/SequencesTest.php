<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Sequences::class)]
#[Medium]
final class SequencesTest extends TestCase
{
    public function testCreateDeclaresTheSequenceColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE SEQUENCE s', []);
        self::assertSame([
          0 => 'last_value bigint NotNull',
          1 => 'log_cnt bigint NotNull',
          2 => 'is_called boolean NotNull',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testOptionsReportsSequenceName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE SEQUENCE s SEQUENCE NAME x', []);
        self::assertSame([
          0 => 'invalid sequence option SEQUENCE NAME',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
