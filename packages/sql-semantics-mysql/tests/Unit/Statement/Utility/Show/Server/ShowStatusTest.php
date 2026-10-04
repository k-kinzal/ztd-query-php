<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;

#[CoversClass(ShowStatus::class)]
#[Medium]
final class ShowStatusTest extends TestCase
{
    public function testDeriveStatementResolvesTheCondition(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW GLOBAL STATUS WHERE Value IS NULL');
        self::assertInstanceOf(ShowStatus::class, $show->statement);
        self::assertSame([], $show->facts->diagnostics);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, $show->statement->scope);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW GLOBAL STATUS WHERE Value IS NULL');
        self::assertInstanceOf(ShowStatus::class, $show->statement);
        self::assertSame('Value', $show->facts->relation($show->statement)->shape->slots[1]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW GLOBAL STATUS WHERE `Value` IS NULL', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW GLOBAL STATUS WHERE Value IS NULL')->toString());
    }
}
