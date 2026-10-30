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
use SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole;
use Tests\Generator\MySql\Statement\Account\AlterDefaultRoleGenerator;

/**
 * Searches for AlterDefaultRole values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class AlterDefaultRoleProperties extends TestCase
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
        $this->forAll(new AlterDefaultRoleGenerator($release))->withMaxSize(10)->then(static function (AlterDefaultRole $statement) use ($release): void {
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
     * Every request completes when the same generated input creates and grants its roles.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(AlterDefaultRoleGenerator::withPrerequisites($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            [$createUser, $createRoles, $grant, $alter] = $sequence;
            $execution->statement($createUser);
            $execution->statement($createRoles);
            $execution->statement($grant);
            $execution->statement($alter);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }
}
