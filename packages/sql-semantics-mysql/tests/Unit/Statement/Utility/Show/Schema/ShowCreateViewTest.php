<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowCreateView::class)]
#[Medium]
final class ShowCreateViewTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.7.44'))->diagnose('SHOW CREATE VIEW db.v');
        self::assertInstanceOf(ShowCreateView::class, $show->statement);
        self::assertSame('Create View', $show->field(1)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE VIEW db.v', (new Inspection('mysql-5.7.44'))->diagnose('SHOW CREATE VIEW db.v')->toString());
    }

    public function testDeriveStatementRefusesABaseTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('SHOW CREATE VIEW t', [$table, $view])->facts->diagnostics));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('SHOW CREATE VIEW v', [$table, $view])->facts->diagnostics));
    }
}
