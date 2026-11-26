<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropViewLowering;
use SqlSemantics\Platform\MySql\Statement\View\DropView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropViewLowering::class)]
#[Medium]
final class DropViewLoweringTest extends TestCase
{
    public function testLowerResolvesTheRequestedViewAndKnownAbsence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $view = $inspection->inspect('CREATE VIEW app.active_rows AS SELECT id FROM app.events', [$table]);
        $statement = $inspection->inspect('DROP VIEW IF EXISTS app.active_rows, app.missing', [$table, $view]);
        self::assertInstanceOf(DropView::class, $statement->statement);

        $remove = (new DropViewLowering())->lower($statement->binding(), $statement->statement);

        self::assertSame('DROP VIEW IF EXISTS `app`.`active_rows`, `app`.`missing`', $remove->toString());
    }
}
