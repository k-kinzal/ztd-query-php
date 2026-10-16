<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class StructureDatabaseTransitionTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, Statement, ?int}>
     */
    public static function providerStructures(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            yield $release->value . ':listing' => [$release, new ShowTables(), null];
            yield $release->value . ':missing table' => [$release, new ShowCreateTable(new InspectedTable(new QualifiedName(new Name('missing')))), 1146];
        }
    }

    #[DataProvider('providerStructures')]
    public function testCompareRetainsTheOriginalObservationAndRepeatsTheSameStructure(GrammarRelease $release, Statement $node, ?int $laterError): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile($release));

        self::assertTrue($observation['matches'], serialize($observation));
        self::assertSame([1046, '3D000', 'No database selected'], $observation['native']['error']);
        self::assertCount(3, $observation['transitions']);
        self::assertSame([true, true, true], array_column($observation['transitions'], 'matches'));
        $repeated = $observation['transitions'][2];
        self::assertSame($observation['sql'], $repeated['sql']);
        $error = $repeated['native']['error'] ?? null;
        self::assertSame($laterError, is_array($error) ? $error[0] : null);
    }
}
