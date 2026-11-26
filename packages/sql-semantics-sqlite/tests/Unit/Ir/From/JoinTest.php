<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(Join::class)]
#[Small]
final class JoinTest extends TestCase
{
    public function testStoresTheExactRightInputAndPredicate(): void
    {
        $source = new TableSource(new Identifier('main'), new Identifier('scores'), []);
        $predicate = new NullLiteral();
        $join = new Join(JoinKind::Left, $source, $predicate);

        self::assertSame($source, $join->source);
        self::assertSame($predicate, $join->on);
        self::assertSame(JoinKind::Left, $join->kind);
    }
}
