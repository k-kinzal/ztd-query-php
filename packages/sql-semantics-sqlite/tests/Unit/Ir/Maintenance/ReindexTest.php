<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;

#[CoversClass(Reindex::class)]
#[Small]
final class ReindexTest extends TestCase
{
    public function testToStringDistinguishesAllIndexesAndBuiltinCollation(): void
    {
        self::assertSame('REINDEX', (new Reindex())->toString());
        self::assertSame('REINDEX "RTRIM"', (new Reindex(Collation::RightTrim))->toString());
    }

    public function testToStringKeepsAnAbsoluteConstraintIndex(): void
    {
        $target = new IndexTarget(new Identifier('main'), new Identifier('sqlite_autoindex_entries_1'), new Identifier('entries'));

        self::assertSame('REINDEX "main"."sqlite_autoindex_entries_1"', (new Reindex($target))->toString());
    }

    public function testProfileIdIdentifiesTheNativeRelease(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new Reindex())->profileId());
    }
}
