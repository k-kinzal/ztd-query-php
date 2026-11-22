<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Dialect::class)]
#[Medium]
final class DialectTest extends TestCase
{
    public function testDatabaseNamesTheSqliteFamilyAsTheGrammarReleasesDo(): void
    {
        self::assertSame('sqlite', Dialect::Sqlite->database());
        self::assertSame('sqlite', Dialect::Sqlite->value);
    }

    public function testDatabaseSelectsTheSqliteGrammarForAnalysis(): void
    {
        $semantics = new Inspection();

        self::assertSame('sqlite', $semantics->profile()->grammar->database());
        self::assertSame('SELECT 42', $semantics->inspect('select   42')->toString());
    }
}
