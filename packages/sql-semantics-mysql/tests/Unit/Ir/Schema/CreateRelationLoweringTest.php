<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateRelationLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike as ParsedCreateTableLike;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateRelationLowering::class)]
#[Medium]
final class CreateRelationLoweringTest extends TestCase
{
    public function testLowerUsesTheBoundaryOfEachDeclaration(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $view = $inspection->inspect('CREATE VIEW app.active AS SELECT id FROM app.events', [$table]);
        self::assertInstanceOf(ParsedCreateTable::class, $table->statement);
        self::assertInstanceOf(ParsedCreateView::class, $view->statement);

        $lowering = new CreateRelationLowering();
        self::assertInstanceOf(CreateTable::class, $lowering->lower($table->binding(), $table->statement));
        self::assertInstanceOf(CreateView::class, $lowering->lower($view->binding(), $view->statement));
    }

    public function testCopyUsesTheSelectedSourceAndIndependentDestinationNamespace(): void
    {
        $inspection = new Inspection();
        $base = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $temporary = $inspection->inspect('CREATE TEMPORARY TABLE app.events (session_id SMALLINT)');
        $query = $inspection->inspect('CREATE TABLE app.copy (LIKE app.events)', [$base, $temporary]);
        self::assertInstanceOf(ParsedCreateTableLike::class, $query->statement);

        $value = (new CreateRelationLowering())->lower($query->binding(), $query->statement);
        self::assertInstanceOf(CreateTableLike::class, $value);
        self::assertSame(ExternalRelationKind::TemporaryTable, $value->source->kind);
        self::assertSame(ExternalRelationKind::BaseTable, $value->destination->kind);
        self::assertSame('CREATE TABLE `app`.`copy` LIKE `app`.`events`', $value->toString());
    }
}
