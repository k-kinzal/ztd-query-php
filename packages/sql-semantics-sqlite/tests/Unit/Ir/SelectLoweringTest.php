<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SelectLowering::class)]
#[Small]
final class SelectLoweringTest extends TestCase
{
    public function testLowerSharesTheResolvedSlot(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE accounts (Id INTEGER, unused INTEGER)');
        $value = (new SelectLowering())->lower($semantics->inspect('SELECT id, accounts.id FROM accounts', [$table])->binding());
        $unqualified = $value->columns[0]->expression;
        $qualified = $value->columns[1]->expression;

        self::assertInstanceOf(ColumnReference::class, $unqualified);
        self::assertInstanceOf(ColumnReference::class, $qualified);
        self::assertSame($unqualified->slot, $qualified->slot);
        self::assertInstanceOf(TableSource::class, $value->source);
        self::assertSame('main', $value->source->schema->value);
        self::assertSame('Id', $value->columns[1]->name->value);
        self::assertCount(1, $value->source->columns);
    }

    public function testLowerIdentifiesAResolvedViewSource(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE accounts (id INTEGER)');
        $view = $inspection->inspect('CREATE VIEW identifiers AS SELECT id FROM main.accounts', [$table]);
        $operation = $inspection->inspect('SELECT * FROM main.identifiers', [$view]);

        $value = (new SelectLowering())->lower($operation->binding());

        self::assertInstanceOf(TableSource::class, $value->source);
        self::assertSame(ExternalRelationKind::View, $value->source->kind);
        self::assertSame('identifiers', $value->source->table->value);
    }

    public function testLowerRefusesAnUnresolvedSource(): void
    {
        $this->expectExceptionMessage('resolved, complete table or view');

        (new SelectLowering())->lower((new Inspection())->inspect('SELECT id FROM accounts')->binding());
    }

    public function testLowerExpandsWildcardFieldsInTheirBoundOrder(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE main.events (id INT, amount INT)');
        $operation = $inspection->inspect('SELECT s.amount AS chosen, s.* FROM main.events AS s', [$declaration]);
        $value = (new SelectLowering())->lower($operation->binding());

        self::assertSame(['chosen', 'id', 'amount'], array_map(static fn ($column): string => $column->name->value, $value->columns));
    }

    public function testLowerSeparatesTwoOccurrencesOfTheSameLocalDefinition(): void
    {
        $operation = (new Inspection())->inspect(
            'WITH c(id) AS (VALUES (?02), (4)) SELECT a.id AS first, b.id AS second FROM c a LEFT JOIN c b ON a.id=b.id'
        );
        $value = (new SelectLowering())->lower($operation->binding());

        self::assertInstanceOf(LocalSource::class, $value->source);
        self::assertSame($value->source, $value->joins[0]->source);
        self::assertEquals(new ColumnReference(0, 0), $value->columns[0]->expression);
        self::assertEquals(new ColumnReference(0, 1), $value->columns[1]->expression);
        self::assertSame(1, substr_count($value->toString(), 'AS (VALUES (?02), (4))'));
    }
}
