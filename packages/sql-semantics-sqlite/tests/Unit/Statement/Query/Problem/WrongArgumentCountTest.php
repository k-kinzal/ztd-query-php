<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\WrongArgumentCount;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WrongArgumentCount::class)]
#[Medium]
final class WrongArgumentCountTest extends TestCase
{
    public function testMessageNamesTheFunction(): void
    {
        $problem = new WrongArgumentCount(new Name('Abs'), 2);

        self::assertSame('wrong number of arguments to function Abs()', $problem->message());
        self::assertSame(2, $problem->arguments);
        self::assertSame('Abs', $problem->function->value);
    }

    public function testMessageIsReportedForACallWithTooManyArguments(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1, 2)');

        self::assertInstanceOf(WrongArgumentCount::class, $query->facts->diagnostics[0]);
        self::assertSame(2, $query->facts->diagnostics[0]->arguments);
        self::assertSame('wrong number of arguments to function abs()', $query->facts->diagnostics[0]->message());
    }
}
