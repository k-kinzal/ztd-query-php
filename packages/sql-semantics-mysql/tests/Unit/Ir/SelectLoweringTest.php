<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(SelectLowering::class)]
#[Small]
final class SelectLoweringTest extends TestCase
{
    public function testLowerSharesTheResolvedSlot(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.accounts (Id INTEGER, unused INTEGER)');
        $value = (new SelectLowering())->lower($semantics->inspect('SELECT id, accounts.id FROM ir_fixture.accounts', [$table]));
        $unqualified = $value->columns[0]->expression;
        $qualified = $value->columns[1]->expression;

        self::assertInstanceOf(ColumnReference::class, $unqualified);
        self::assertInstanceOf(ColumnReference::class, $qualified);
        self::assertSame($unqualified->slot, $qualified->slot);
        self::assertSame('ir_fixture', $value->source?->schema->value);
        self::assertSame('id', $value->columns[1]->name->value);
        self::assertCount(1, $value->source->columns);
    }

    public function testLowerRefusesAnUnresolvedSource(): void
    {
        $this->expectExceptionMessage('resolved, complete base table');

        (new SelectLowering())->lower((new Semantics())->inspect('SELECT id FROM ir_fixture.accounts'));
    }
}
