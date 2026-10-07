<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings as Resolution;

#[CoversClass(Settings::class)]
#[Small]
final class SettingsTest extends TestCase
{
    public function testResolutionAnswersTheServerDefaultsForTheConnectionCollation(): void
    {
        $resolution = (new Settings(Collation::known('latin1_swedish_ci'), new SqlModes([]), 6))->resolution();

        self::assertSame(['latin1_swedish_ci', 6], [$resolution->connection->name, $resolution->divPrecisionIncrement]);
    }

    public function testResolutionAnswersTheResolutionTheSettingsWereGiven(): void
    {
        $resolution = new Resolution(Collation::known('utf8mb4_bin'), 2);

        self::assertSame($resolution, (new Settings(Collation::known('utf8mb4_0900_ai_ci'), new SqlModes([]), 4, '', '8.4.7', $resolution))->resolution());
    }

    public function testReleaseAnswersTheReleaseOfTheVersion(): void
    {
        self::assertSame(GrammarRelease::MySql910, (new Settings(Collation::known('utf8mb4_0900_ai_ci'), new SqlModes([]), 4, '', '9.1.0'))->release());
    }

    public function testReleaseFallsBackToTheDefaultReleaseForAnUnknownVersion(): void
    {
        self::assertSame(GrammarRelease::MySql847, (new Settings(Collation::known('utf8mb4_0900_ai_ci'), new SqlModes([]), 4, '', '1.0.0'))->release());
    }
}
