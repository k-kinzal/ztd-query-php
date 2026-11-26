<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowDatabases::class)]
#[Medium]
final class ShowDatabasesTest extends TestCase
{
    public function testDeriveStatementResolvesTheConditionAgainstTheRows(): void
    {
        $show = (new Inspection())->inspect("SHOW DATABASES WHERE `Database` LIKE 'a%'");
        self::assertInstanceOf(ShowDatabases::class, $show->statement);
        self::assertSame([], $show->facts->diagnostics);
        self::assertSame('Database', $show->field(0)->name?->value);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Inspection())->inspect("SHOW DATABASES WHERE `Database` LIKE 'a%'");
        self::assertInstanceOf(ShowDatabases::class, $show->statement);
        self::assertSame('Database', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW DATABASES WHERE `Database` LIKE 'a%'", (new Inspection())->inspect("SHOW DATABASES WHERE `Database` LIKE 'a%'")->toString());
    }
}
