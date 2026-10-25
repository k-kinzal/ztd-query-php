<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList as Model;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class ParameterListProperties extends TestCase
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
     * @return iterable<string, array{string, list<Parameter>, list<NumberLiteral>}>
     */
    public static function providerParametersParameters(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                $choices = new Choices('routine-parameters:' . $seed);
                $positions = range(0, $choices->integer(0, 7));
                $parameters = array_map(static fn (int $position): Parameter => new Parameter(new Name($choices->one(['a', 'É', '猫', ' x', "tab\t"]) . $position), new Integral(IntegralKind::Int)), $positions);
                $arguments = array_map(static fn (int $position): NumberLiteral => new NumberLiteral((string) $choices->integer(0, 100000)), $positions);
                yield $release . ':' . $seed => [$release, $parameters, $arguments];
            }
        }
    }

    /**
     * @return iterable<string, array{string, list<Parameter>, list<NumberLiteral>}>
     */
    public static function providerParametersLegacyParameters(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                $choices = new Choices('legacy-parameters:' . $seed);
                $name = $choices->one(['', str_repeat('猫', $choices->integer(65, 80)), 'p' . $choices->integer(0, 100000) . ' ']);
                yield 'legacy:' . $release . ':' . $seed => [$release, [new Parameter(new Name($name), new Integral(IntegralKind::Int))], [new NumberLiteral((string) $choices->integer(0, 100000))]];
            }
        }
    }

    /**
     * @param list<Parameter> $parameters The generated declarations with distinct names
     * @param list<NumberLiteral> $arguments The values assigned by position
     */
    #[DataProvider('providerParametersParameters')]
    #[DataProvider('providerParametersLegacyParameters')]
    #[Test]
    public function satisfiesParameterListsBindEveryArgumentToItsDeclaredPosition(string $release, array $parameters, array $arguments): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $name = new QualifiedName(new Name('p'));
        $body = new QueryRoot(new Select([], array_map(static fn (Parameter $parameter): SelectExpression => new SelectExpression(new ColumnUse($parameter->name)), $parameters)));
        $statement1 = (new Embedding())->statement(new CreateProcedure($name, new Model($parameters), $body));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $created = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new ProcedureCall($name, $arguments));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $called = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([array_map(static fn (NumberLiteral $value): string => $value->text, $arguments)], $called->replies[0]->rows);
    }
}
