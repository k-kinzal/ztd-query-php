<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use Tests\Support\Inspection;

#[CoversClass(SelectLowering::class)]
#[Small]
final class SelectLoweringTest extends TestCase
{
    public function testLowerSharesTheResolvedSlot(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE public.accounts (Id INTEGER, unused INTEGER)');
        $value = (new SelectLowering())->lower($semantics->inspect('SELECT id, accounts.id FROM public.accounts', [$table])->binding());
        $unqualified = $value->columns[0]->expression;
        $qualified = $value->columns[1]->expression;

        self::assertInstanceOf(ColumnReference::class, $unqualified);
        self::assertInstanceOf(ColumnReference::class, $qualified);
        self::assertSame($unqualified->slot, $qualified->slot);
        self::assertSame('public', $value->source?->schema->value);
        self::assertSame('id', $value->columns[1]->name->value);
        self::assertCount(1, $value->source->columns);
    }

    public function testLowerRefusesAnUnresolvedSource(): void
    {
        $this->expectExceptionMessage('resolved, complete base table');

        (new SelectLowering())->lower((new Inspection())->inspect('SELECT id FROM public.accounts')->binding());
    }
}
