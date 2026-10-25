<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Set;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames as Model;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class SetNamesProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerDefaultEncodingHistories(): iterable
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
    #[DataProvider('providerDefaultEncodingHistories')]
    #[Test]
    public function satisfiesUnchangedDefaultEncodingTracksTheGlobalClientState(string $release, int $seed): void
    {
        $choices = new Choices('default-encoding:' . $seed);
        [$charset, $collation, $other] = $choices->one([['latin1', 'latin1_bin', 'utf8mb4'], ['latin1', 'latin1_swedish_ci', 'utf8mb4'], ['utf8mb4', 'utf8mb4_bin', 'latin1'], ['utf8mb4', 'utf8mb4_general_ci', 'latin1']]);
        $statement = new SetVariables([new Model(new CharsetName(null), new CollationName(new Name($collation)))]);
        $session = (new Instance($release))->connect();
        foreach ([$charset, $other, $charset] as $phase => $value) {
            $session->query('SET GLOBAL character_set_server=' . $other . ', GLOBAL character_set_client=' . $value);
            $session->text = 'unrelated invalid input';
            $statement1 = (new Embedding())->statement($statement);
            $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            try {
                $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
                $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), $parameters1);
            } catch (SqlError $executionError) {
                $result1 = $executionError;
                (new Execution($session))->failed($executionError);
            }
            $outcome = $result1;
            self::assertSame($phase === 1 ? 1253 : null, ($outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null)[0] ?? null);
            self::assertSame($charset, $session->variables->read('character_set_client'));
            self::assertSame($collation, $session->variables->read('collation_connection'));
            self::assertSame('unrelated invalid input', $session->text);
        }
    }
}
