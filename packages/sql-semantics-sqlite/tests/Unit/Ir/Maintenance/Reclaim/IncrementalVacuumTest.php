<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Reclaim;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\PageReclaimLimit;

#[CoversClass(IncrementalVacuum::class)]
#[Small]
final class IncrementalVacuumTest extends TestCase
{
    public function testToStringKeepsASelectedTargetAndAFiniteLimit(): void
    {
        $request = new IncrementalVacuum(new Identifier('archive'), new PageReclaimLimit(2));

        self::assertSame('PRAGMA "main".incremental_vacuum', (new IncrementalVacuum())->toString());
        self::assertSame('PRAGMA "archive".incremental_vacuum(2)', $request->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new IncrementalVacuum())->profileId());
    }
}
