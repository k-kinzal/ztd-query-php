<?php

declare(strict_types=1);

namespace Tests\Property;

use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use Tests\Generator\MySql\Statement\Server\Database\DropDatabaseGenerator;

/**
 * Searches for DropDatabase values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class DropDatabaseProperties extends TestCase
{
    use TestTrait;

    /**
     * With no generated prerequisite state, execution may complete or refuse the
     * statement as SQL. Any representation or implementation failure escapes.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionConsumesTheAnalyzedValue(GrammarRelease $release): void
    {
        $this->forAll(new DropDatabaseGenerator($release))->withMaxSize(10)->then(static function (DropDatabase $statement) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            try {
                $execution->statement($statement);
            } catch (SqlError) {
                // SQL refusal is an allowed outcome without prerequisite state.
            }
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
