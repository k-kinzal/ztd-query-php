<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaViewOrder::class)]
final class SchemaViewOrderTest extends TestCase
{
    public function testCheckRequiresThePrecedingLocalTable(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE fresh.readings (amount integer)']);
        $view = $semantics->analyze('CREATE VIEW fresh.recent AS SELECT amount FROM fresh.readings', $context);
        self::assertInstanceOf(CreateView::class, $view);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema view must resolve its local input to a preceding base table.');

        new CreateSchema(new Identifier('fresh'), views: [$view]);
    }

    public function testCheckRequiresExactLocalColumnKinds(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE fresh.readings (amount integer)']);
        $view = $semantics->analyze('CREATE VIEW fresh.recent AS SELECT amount FROM fresh.readings', $context);
        $table = $semantics->analyze('CREATE TABLE fresh.readings (amount bigint)');
        self::assertInstanceOf(CreateView::class, $view);
        self::assertInstanceOf(CreateTable::class, $table);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema view requires each local input field in the checked native kind.');

        new CreateSchema(new Identifier('fresh'), tables: [$table], views: [$view]);
    }
}
