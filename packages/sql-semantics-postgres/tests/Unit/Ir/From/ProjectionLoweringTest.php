<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ProjectionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ProjectionLowering::class)]
#[Small]
final class ProjectionLoweringTest extends TestCase
{
    public function testLowerResolvesGroupedAndBareReadsToTheSameNativeSlot(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.bounds (wide bigint)');
        $operation = $inspection->inspect('SELECT ((wide)), bounds.wide FROM public.bounds', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $bound = $operation->binding();
        $source = new SourceBinding($bound, $statement->from);
        $types = [];

        $columns = (new ProjectionLowering())->lower($bound, $statement, $source, true, $types, null, true);
        $first = $columns[0]->expression;
        $second = $columns[1]->expression;
        self::assertInstanceOf(WideIntegerColumnReference::class, $first);
        self::assertInstanceOf(WideIntegerColumnReference::class, $second);
        self::assertSame($first->slot, $second->slot);
        self::assertSame('wide', $columns[0]->name->value);
    }
}
