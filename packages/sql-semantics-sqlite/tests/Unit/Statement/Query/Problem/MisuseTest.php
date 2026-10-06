<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;

#[CoversClass(Misuse::class)]
#[Medium]
final class MisuseTest extends TestCase
{
    public function testMessageIsTheTextOfTheRule(): void
    {
        $problem = new Misuse(MisuseRule::CircularReference);

        self::assertSame('circular reference', $problem->message());
        self::assertSame(MisuseRule::CircularReference, $problem->rule);
        self::assertSame('row value misused', (new Misuse(MisuseRule::TooManyValueColumns))->message());
    }

    public function testMessageIsReportedForAStarWithoutTables(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT *');

        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::StarWithoutTables, $query->facts->diagnostics[0]->rule);
        self::assertSame('no tables specified', $query->facts->diagnostics[0]->message());
    }
}
