<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Variable;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable as Model;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class SystemVariableProperties extends TestCase
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
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSystemVariableSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When the semantic structure cannot execute
     */
    #[DataProvider('providerSystemVariableSeeds')]
    #[Test]
    public function satisfiesAssignmentAndReadPreserveTheirScopes(string $seed): void
    {
        $choices = new Choices('system-variable:' . $seed);
        $scope = $choices->one([null, ...VariableScope::cases()]);
        $value = $choices->integer(0, 1);
        $session = (new Instance())->connect();
        $statement1 = (new Embedding())->statement(new SetVariables([new SystemAssignment(new SystemTarget(new Name('autocommit'), $scope), new NumberLiteral((string) $value))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $global = in_array($scope, [VariableScope::Global, VariableScope::Persist, VariableScope::PersistOnly], true);
        $statement2 = (new Embedding())->statement(new Model(new Name('autocommit'), $global ? VariableScope::Global : VariableScope::Session));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $result = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[(string) ($scope === VariableScope::PersistOnly ? 1 : $value)]], $result->rows);
    }
}
