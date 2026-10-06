<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;

#[CoversClass(ShowProcedureStatus::class)]
#[Medium]
final class ShowProcedureStatusTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("SHOW PROCEDURE STATUS LIKE 'x'");
        self::assertInstanceOf(ShowProcedureStatus::class, $show->statement);
        self::assertSame('Definer', $show->field(3)->name?->value);
    }

    public function testDeriveRelationShapesTheRowsOfTheRelease(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("SHOW PROCEDURE STATUS LIKE 'x'");
        self::assertInstanceOf(ShowProcedureStatus::class, $show->statement);
        $later = (new Semantics(Dialect::MySql))->analyze('SHOW PROCEDURE STATUS');
        self::assertSame('Language', $later->field(3)->name?->value);
        self::assertSame('Db', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW PROCEDURE STATUS LIKE 'x'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("SHOW PROCEDURE STATUS LIKE 'x'")->toString());
    }
}
