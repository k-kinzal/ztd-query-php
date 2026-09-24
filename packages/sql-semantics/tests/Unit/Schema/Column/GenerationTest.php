<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\AutoIncrementColumn;
use SqlSemantics\Schema\Column\ComputedColumn;
use SqlSemantics\Schema\Column\Generation;
use SqlSemantics\Schema\Column\IdentityColumn;
use SqlSemantics\Schema\Column\SuppliedColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Generation::class)]
#[Medium]
final class GenerationTest extends TestCase
{
    public function testExpressionsListEveryDeclaredValueExpressionPerForm(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT AUTO_INCREMENT PRIMARY KEY, b INT DEFAULT 1, c INT AS (b + 1), d TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)')->tables[0];
        self::assertInstanceOf(AutoIncrementColumn::class, $table->columns[0]->generation);
        self::assertInstanceOf(SuppliedColumn::class, $table->columns[1]->generation);
        self::assertInstanceOf(ComputedColumn::class, $table->columns[2]->generation);
        self::assertInstanceOf(SuppliedColumn::class, $table->columns[3]->generation);
        self::assertSame([0, 1, 1, 2], array_map(static fn ($column): int => count($column->generation->expressions()), $table->columns));
    }

    public function testEveryFormImplementsTheContract(): void
    {
        $forms = [new AutoIncrementColumn(), new SuppliedColumn(), new IdentityColumn(\SqlSemantics\Schema\Column\IdentityMode::Always)];
        self::assertContainsOnlyInstancesOf(Generation::class, $forms);
        self::assertSame([[], [], []], array_map(static fn (Generation $form): array => $form->expressions(), $forms));
    }
}
