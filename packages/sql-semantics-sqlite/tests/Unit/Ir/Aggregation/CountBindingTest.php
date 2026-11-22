<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\CountBinding;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupBinding;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CountBinding::class)]
#[Medium]
final class CountBindingTest extends TestCase
{
    public function testLowerResolvesTheDistinctArgument(): void
    {
        $inspector = new Inspection();
        $table = $inspector->inspect('CREATE TABLE main.items (id COLLATE RTRIM)');
        $operation = $inspector->inspect('SELECT count(DISTINCT id) AS n FROM main.items', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $column = $operation->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(FunctionCall::class, $column->expression);
        $source = new SourceBinding($operation->binding(), $operation->statement->from);
        $binding = new GroupBinding($operation->binding(), $source);
        $count = (new CountBinding())->lower($column->expression, $binding);

        self::assertSame(0, $count->argument?->slot);
        self::assertSame(Collation::RightTrim, $count->distinctCollation);
    }
}
