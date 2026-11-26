<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;

#[CoversClass(Analyze::class)]
#[Small]
final class AnalyzeTest extends TestCase
{
    public function testToStringPreservesTheGlobalAndEmptySchemaScopes(): void
    {
        self::assertSame('ANALYZE', (new Analyze())->toString());
        self::assertSame('ANALYZE ""', (new Analyze(new Identifier('')))->toString());
    }

    public function testToStringKeepsTheBoundIndexCoordinate(): void
    {
        $target = new IndexTarget(new Identifier('temp'), new Identifier('key "x"'), new Identifier('rows'));

        self::assertSame('ANALYZE "temp"."key ""x"""', (new Analyze($target))->toString());
    }

    public function testProfileIdIdentifiesTheNativeStatisticsLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new Analyze())->profileId());
    }
}
