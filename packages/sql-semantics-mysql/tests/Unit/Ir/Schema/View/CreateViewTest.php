<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCheckOption;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCreationPolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewSecurity;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

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
        self::assertSame(ViewAlgorithm::Undefined, $view->installedAlgorithm);
        self::assertStringStartsWith('CREATE ALGORITHM=MERGE ', $view->toString());
    }

    public function testInstalledAlgorithmDerivesTheDistinctPhysicalMergeFallback(): void
    {
        $schema = new Identifier('app');
        $column = new Identifier('value');
        $source = new TableSource($schema, new Identifier('items'), [$column]);
        $selection = new Select([new ResultColumn(new ColumnReference(0), $column)], $source, distinct: true);
        $view = new CreateView(new ViewName($schema, new Identifier('unique_items')), $selection, [new ViewColumn($column)], algorithm: ViewAlgorithm::Merge);

        self::assertSame(ViewAlgorithm::Undefined, $view->installedAlgorithm);
        self::assertStringContainsString(' AS SELECT DISTINCT ', $view->toString());
    }

    public function testCheckPolicyCanBeSetOnAMergeableConstantProjection(): void
    {
        $schema = new Identifier('app');
        $column = new Identifier('value');
        $name = new ViewName($schema, new Identifier('checked_view'));
        $source = new TableSource($schema, new Identifier('items'), [new Identifier('id')]);
        $selection = new Select([new ResultColumn(new IntegerLiteral('42'), $column)], $source, GrammarRelease::MySql5651);
        $view = new CreateView($name, $selection, [new ViewColumn($column)], checkOption: ViewCheckOption::Local);

        self::assertSame(ViewCheckOption::Local, $view->checkOption);
        self::assertStringEndsWith(' WITH LOCAL CHECK OPTION', $view->toString());
    }
}
