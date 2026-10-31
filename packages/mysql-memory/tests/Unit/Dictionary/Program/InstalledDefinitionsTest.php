<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Program;

use MySqlMemory\Dictionary\Program\InstalledDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(InstalledDefinitions::class)]
#[Small]
final class InstalledDefinitionsTest extends TestCase
{
    public function testOfSelectsTheReleaseSpecificSysDeclarations(): void
    {
        $legacy = InstalledDefinitions::of(GrammarRelease::MySql5744)['routines'][0];
        $current = InstalledDefinitions::of(GrammarRelease::MySql847)['routines'][0];
        self::assertSame(['routines' => [], 'parameters' => []], InstalledDefinitions::of(GrammarRelease::MySql5651));
        self::assertSame(['utf8', 192], [$legacy[8], $legacy[4]]);
        self::assertSame(['utf8mb4', 256], [$current[8], $current[4]]);
    }
}
