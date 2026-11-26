<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Testing\PostgreSql\Inspection;

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
        self::assertInstanceOf(TableSource::class, $value->source);
        self::assertSame('public', $value->source->schema->value);
        self::assertSame('id', $value->columns[1]->name->value);
        self::assertCount(1, $value->source->columns);
    }

    public function testLowerRefusesAnUnresolvedSource(): void
    {
        $this->expectExceptionMessage('resolved, complete local relation declaration');

        (new SelectLowering())->lower((new Inspection())->inspect('SELECT id FROM public.accounts')->binding());
    }

    public function testLowerExpandsWildcardFieldsInTheirBoundOrder(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE app.events (id INT, amount INT)');
        $operation = $inspection->inspect('SELECT s.amount AS chosen, s.* FROM app.events AS s', [$declaration]);
        $value = (new SelectLowering())->lower($operation->binding());

        self::assertSame(['chosen', 'id', 'amount'], array_map(static fn ($column): string => $column->name->value, $value->columns));
    }
}
