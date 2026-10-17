<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Database fixtures preserve the complete requested identifier and classify
 * no unrelated SQL error as evidence for a missing-schema transition.
 */
#[CoversNothing]
#[Small]
final class DatabaseFixtureTest extends TestCase
{
    /**
     * A fixture name is semantic data, including embedded delimiters and Unicode.
     */
    public function testDatabaseRetainsEveryReportedIdentifierCharacter(): void
    {
        $compare = new Comparison();
        $names = ['ordinary', "a'b", 'a`b', 'あ', "a\nb"];
        self::assertSame($names, array_map(static fn (string $name): ?string => $compare->database(['error' => [1049, '42000', "Unknown database '" . $name . "'"]])?->value, $names));
        self::assertSame('structure_fixture', $compare->database(['error' => [1046, '3D000', 'No database selected']])?->value);
        self::assertNull($compare->database(['error' => [1146, '42S02', 'A table is missing']]));
        self::assertNull($compare->database(['results' => []]));
    }

    /**
     * Unknown error shapes stay failures instead of silently losing a required history.
     */
    public function testDatabaseRejectsAnUnrecognizedUnknownDatabaseObservation(): void
    {
        $this->expectException(RuntimeException::class);
        (new Comparison())->database(['error' => [1049, '42000', 'Unrecognized diagnostic']]);
    }
}
