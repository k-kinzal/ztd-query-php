<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Literal;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
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
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter as Model;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ParameterProperties extends TestCase
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
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
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
     * @return iterable<string, array{int, int}>
     */
    public static function providerParameterBindings(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            $choices = new Choices('binding:' . $seed);
            yield (string) $seed => [$choices->integer(-1000, 1000), $choices->integer(-1000, 1000)];
        }
    }

    /**
     * A derived table is resolved before the projection but reads the second input slot.
     */
    #[DataProvider('providerParameterBindings')]
    #[Test]
    public function satisfiesInputsBindInWrittenOrderRatherThanDerivationOrder(int $projected, int $nested): void
    {
        $query = new Select([], [new SelectExpression(new Model())], new DerivedTable(new Select([], [new SelectExpression(new Model(), new Name('a'))]), new Name('t')), new Model());
        $session4 = (new Instance('8.4.7'))->connect();
        $statement4 = (new Embedding())->statement($query);
        $parameters4 = [[$projected, Domain::integer()], [$nested, Domain::integer()], [1, Domain::integer()]];
        $session4->activity->begin();
        StatementCounters::received($session4);
        $session4->hinted = [];
        $session4->dots = [];
        $operation4 = (new Binding())->operation($session4, $statement4, $parameters4, new LanguageProfile($session4->semantics()->profile()->grammar, $session4->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $reply = (new Execution($session4))->perform($operation4, (new Dispatcher())->command($operation4->statement), $parameters4);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) $projected]], $reply->rows);
    }

    /**
     * The named extension preserves occurrence order alongside positional markers.
     */
    #[DataProvider('providerParameterBindings')]
    #[Test]
    public function satisfiesNamedAndPositionalInputsBindAcrossNestedScopes(int $projected, int $nested): void
    {
        $query = new Select([], [new SelectExpression(new Model(':projected'))], new DerivedTable(new Select([], [new SelectExpression(new Model(':nested'), new Name('a'))]), new Name('t')), new Model());
        $session5 = (new Instance('8.4.7'))->connect();
        $statement5 = (new Embedding())->statement($query);
        $parameters5 = [[$projected, Domain::integer()], [$nested, Domain::integer()], [1, Domain::integer()]];
        $session5->activity->begin();
        StatementCounters::received($session5);
        $session5->hinted = [];
        $session5->dots = [];
        $operation5 = (new Binding())->operation($session5, $statement5, $parameters5, new LanguageProfile($session5->semantics()->profile()->grammar, $session5->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
        $reply = (new Execution($session5))->perform($operation5, (new Dispatcher())->command($operation5->statement), $parameters5);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) $projected]], $reply->rows);
    }
}
