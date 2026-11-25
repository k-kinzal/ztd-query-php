<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCreationPolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewSecurity;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(CreateView::class)]
#[Small]
final class CreateViewTest extends TestCase
{
    public function testToStringPreservesTheCreationPolicyAndHeading(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('answer'));
        $query = new Select([new ResultColumn(new IntegerLiteral('42'), new Identifier('original'))]);
        $view = new CreateView(
            $name,
            $query,
            [new ViewColumn(new Identifier('answer'))],
            ViewCreationPolicy::Replace,
            ViewAlgorithm::TempTable,
            ViewSecurity::Invoker
        );

        self::assertSame('CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW `app`.`answer` (`answer`) AS SELECT 42 AS `__ztd_c0`', $view->toString());
    }

    public function testProfileIdUsesTheReleaseOfTheStoredQuery(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('answer'));
        $column = new Identifier('answer');
        $query = new Select([new ResultColumn(new IntegerLiteral('42'), $column)]);
        $view = new CreateView($name, $query, [new ViewColumn($column)]);

        self::assertSame('mysql-9.1.0/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/view-definition', $view->profileId());
    }

    public function testInstalledAlgorithmDerivesTheSourceFreeMergeFallback(): void
    {
        $column = new Identifier('answer');
        $name = new ViewName(new Identifier('app'), $column);
        $selection = new Select([new ResultColumn(new IntegerLiteral('42'), $column)]);
        $view = new CreateView($name, $selection, [new ViewColumn($column)], algorithm: ViewAlgorithm::Merge);

        self::assertSame(ViewAlgorithm::Merge, $view->algorithm);
        self::assertSame(ViewAlgorithm::Undefined, $view->installedAlgorithm());
        self::assertStringStartsWith('CREATE ALGORITHM=MERGE ', $view->toString());
    }
}
