<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(Join::class)]
#[Small]
final class JoinTest extends TestCase
{
    public function testInputsKeepOrientationsAndRepeatedTables(): void
    {
        $a = new TableSource(new Identifier('ir_fixture'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('ir_fixture'), new Identifier('b'), []);
        $nested = new Join(JoinKind::Cross, $b, $a);
        $join = new Join(JoinKind::Cross, $a, $nested);

        $snapshot = $join->inputs();
        array_pop($snapshot);

        self::assertSame([$a, $b, $a], $join->inputs());
    }

    public function testCrossRejectsAnAttachedPredicate(): void
    {
        $table = new TableSource(new Identifier('ir_fixture'), new Identifier('t'), []);
        $this->expectExceptionMessage('Only CROSS has no join predicate.');

        new Join(JoinKind::Cross, $table, $table, new BooleanLiteral(true));
    }
}
