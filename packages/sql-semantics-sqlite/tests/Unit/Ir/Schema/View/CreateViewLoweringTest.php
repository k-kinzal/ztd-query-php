<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateViewLowering::class)]
#[Small]
final class CreateViewLoweringTest extends TestCase
{
    public function testLowerResolvesAViewLocalDefinition(): void
    {
        $operation = (new Inspection())->inspect('CREATE VIEW sample AS WITH local(value) AS (VALUES (9)) SELECT value FROM local');
        self::assertInstanceOf(CreateView::class, $operation->statement);

        $view = (new CreateViewLowering())->lower($operation->binding(), $operation->statement);

        self::assertInstanceOf(Select::class, $view->query);
        self::assertInstanceOf(LocalSource::class, $view->query->source);
        self::assertSame('value', $view->columns[0]->value);
    }

    public function testLowerUsesTheGroupedDefinitionAndExposesItsMode(): void
    {
        $operation = (new Inspection())->inspect('CREATE VIEW counts(n) AS SELECT count(*)');
        self::assertInstanceOf(CreateView::class, $operation->statement);

        $view = (new CreateViewLowering())->lower($operation->binding(), $operation->statement);

        self::assertInstanceOf(GroupedSelect::class, $view->query);
        self::assertSame('n', $view->columns[0]->value);
        self::assertSame($view->query->profileId(), $view->profileId());
    }

    public function testLowerResolvesThePermanentSourceUnderTempShadowing(): void
    {
        $inspection = new Inspection();
        $main = $inspection->inspect('CREATE TABLE main.source (value INTEGER)');
        $temp = $inspection->inspect('CREATE TEMP TABLE source (value TEXT)');
        $operation = $inspection->inspect(
            'CREATE VIEW sample("true") AS SELECT value FROM source',
            $inspection->context([$main, $temp], true),
        );
        self::assertInstanceOf(CreateView::class, $operation->statement);

        $view = (new CreateViewLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame('column1', $view->columns[0]->value);
        self::assertInstanceOf(Select::class, $view->query);
        self::assertInstanceOf(TableSource::class, $view->query->source);
        self::assertSame('main', $view->query->source->schema->value);
    }
}
