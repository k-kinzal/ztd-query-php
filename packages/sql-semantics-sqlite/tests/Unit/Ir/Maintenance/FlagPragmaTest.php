<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;

#[CoversClass(FlagPragma::class)]
#[Small]
final class FlagPragmaTest extends TestCase
{
    public function testToStringWritesAReadAndAChangeWithExplicitScope(): void
    {
        self::assertSame('PRAGMA foreign_keys', (new FlagPragma(FlagName::ForeignKeys))->toString());
        self::assertSame(
            'PRAGMA "archive ""1""".writable_schema = RESET',
            (new FlagPragma(FlagName::WritableSchema, FlagChange::Reset, new Identifier('archive "1"')))->toString(),
        );
    }

    public function testProfileIdIdentifiesThePinnedSqliteProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new FlagPragma(FlagName::QueryOnly))->profileId());
    }
}
