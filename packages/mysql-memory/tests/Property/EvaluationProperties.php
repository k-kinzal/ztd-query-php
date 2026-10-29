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
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use Tests\Generator\MySql\Statement\Dml\EvaluationGenerator;

/**
 * Searches for Evaluation values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class EvaluationProperties extends TestCase
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
        $this->forAll(new EvaluationGenerator($release))->withMaxSize(10)->then(static function (Evaluation $statement) use ($release): void {
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
        yield GrammarRelease::MySql5651->value => [GrammarRelease::MySql5651];
        yield GrammarRelease::MySql5744->value => [GrammarRelease::MySql5744];
        yield GrammarRelease::MySql8044->value => [GrammarRelease::MySql8044];
        yield GrammarRelease::MySql810->value => [GrammarRelease::MySql810];
        yield GrammarRelease::MySql820->value => [GrammarRelease::MySql820];
        yield GrammarRelease::MySql830->value => [GrammarRelease::MySql830];
        yield GrammarRelease::MySql847->value => [GrammarRelease::MySql847];
        yield GrammarRelease::MySql901->value => [GrammarRelease::MySql901];
        yield GrammarRelease::MySql910->value => [GrammarRelease::MySql910];
    }
}
