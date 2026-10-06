<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

#[CoversClass(Dialect::class)]
#[Medium]
final class DialectTest extends TestCase
{
    public function testDatabaseNamesTheFamilyAsTheGrammarReleasesDo(): void
    {
        self::assertSame('sqlite', Sqlite::Sqlite->database());
        self::assertSame('sqlite', (new Semantics(Sqlite::Sqlite))->profile()->grammar->database());
    }
}
