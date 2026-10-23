<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Session;

use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Result\Observation;

/**
 * The unchanged DEFAULT structure follows the global client encoding through
 * successful and conflicting states. Native histories independently compare
 * the same assignments, conditions and connection values on five releases.
 */
#[CoversNothing]
#[Medium]
final class DefaultEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Preserves one semantic request across successful and conflicting global settings.
     */
    #[DataProvider('providerHistories')]
    public function testUnchangedDefaultEncodingTracksTheGlobalClientState(string $release, int $seed): void
    {
        $choices = new Choices('default-encoding:' . $seed);
        [$charset, $collation, $other] = $choices->one([
            ['latin1', 'latin1_bin', 'utf8mb4'],
            ['latin1', 'latin1_swedish_ci', 'utf8mb4'],
            ['utf8mb4', 'utf8mb4_bin', 'latin1'],
            ['utf8mb4', 'utf8mb4_general_ci', 'latin1'],
        ]);
        $statement = new SetVariables([new SetNames(new CharsetName(null), new CollationName(new Name($collation)))]);
        $session = (new Instance($release))->connect();
        $memory = new Observation();
        foreach ([$charset, $other, $charset] as $phase => $value) {
            $session->query('SET GLOBAL character_set_server=' . $other . ', GLOBAL character_set_client=' . $value);
            $session->text = 'unrelated invalid input';
            $outcome = $memory->observe($session, $statement);
            self::assertSame($phase === 1 ? 1253 : null, $outcome['error'][0] ?? null);
            self::assertSame($charset, $session->variables->read('character_set_client'));
            self::assertSame($collation, $session->variables->read('collation_connection'));
            self::assertSame('unrelated invalid input', $session->text);
        }
    }
}
