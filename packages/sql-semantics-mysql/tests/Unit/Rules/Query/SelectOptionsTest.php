<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SelectOptions;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Warning;

#[CoversClass(SelectOptions::class)]
#[Medium]
final class SelectOptionsTest extends TestCase
{
    public function testRaiseWarnsOfTheQueryCacheModifiersIn57AndRefusesTwoInARow(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT SQL_CACHE SQL_CACHE SQL_NO_CACHE 1');

        self::assertSame(["'SQL_CACHE' is deprecated and will be removed in a future release.", "'SQL_CACHE' is deprecated and will be removed in a future release.", "Option 'SQL_CACHE' used twice in statement"], array_map(static fn (Warning $warning): string => $warning->message(), $operation->facts->warnings));
        self::assertSame(["Option 'SQL_CACHE' used twice in statement"], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRaiseSeparatesTheModifiersOf57ByAnotherOneAndNotThoseOf56(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT SQL_NO_CACHE ALL SQL_CACHE 1');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT SQL_NO_CACHE ALL SQL_CACHE 1');

        self::assertSame([], $modern->facts->diagnostics);
        self::assertSame(['Incorrect usage of SQL_NO_CACHE and SQL_CACHE'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $legacy->facts->diagnostics));
        self::assertSame(['Incorrect usage of SQL_NO_CACHE and SQL_CACHE'], array_map(static fn (Warning $warning): string => $warning->message(), $legacy->facts->warnings));
    }
}
