<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowFunctionStatus::class)]
#[Medium]
final class ShowFunctionStatusTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-8.0.44'))->inspect("SHOW FUNCTION STATUS LIKE 'x'");
        self::assertInstanceOf(ShowFunctionStatus::class, $show->statement);
        self::assertSame('Definer', $show->field(3)->name?->value);
    }

    public function testDeriveRelationShapesTheRowsOfTheRelease(): void
    {
        $show = (new Inspection('mysql-8.0.44'))->inspect("SHOW FUNCTION STATUS LIKE 'x'");
        self::assertInstanceOf(ShowFunctionStatus::class, $show->statement);
        $later = (new Inspection())->inspect('SHOW FUNCTION STATUS');
        self::assertSame('Language', $later->field(3)->name?->value);
        self::assertSame('Db', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW FUNCTION STATUS LIKE 'x'", (new Inspection('mysql-8.0.44'))->inspect("SHOW FUNCTION STATUS LIKE 'x'")->toString());
    }
}
