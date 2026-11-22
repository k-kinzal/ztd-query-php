<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateViewLowering::class)]
#[Small]
final class CreateViewLoweringTest extends TestCase
{
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
