<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Condition;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
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
final class SqlStateProperties extends TestCase
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
     * @return iterable<string, array{string, string}>
     */
    public static function providerSqlStateStates(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                $choices = new Choices('sqlstate:' . $seed);
                $alphabet = str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ');
                $value = implode('', array_map(static fn (int $index): string => $choices->one($alphabet), range(0, 4)));
                $first = $choices->one($alphabet);
                $class = $first . $choices->one($first === '0' ? array_slice($alphabet, 1) : $alphabet);
                $value = ($seed % 3 === 0 ? '01' : ($seed % 3 === 1 ? '02' : $class)) . substr($value, 2);
                yield $release . ':' . $seed => [$release, $value];
            }
        }
    }

    /**
     * A declared handler consumes the condition and execution continues after SIGNAL.
     */
    #[DataProvider('providerSqlStateStates')]
    #[Test]
    public function satisfiesConstructedConditionsAreHandledAndExecutionContinues(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $handler = new HandlerDeclaration(HandlerAction::Continue, [new Model(new Text($value))], new SetVariables([new UserAssignment(new UserVariable(new Name('handled')), new NumberLiteral('1'))]));
        $body = new Block([$handler], [new Signal(new Model(new Text($value))), new SetVariables([new UserAssignment(new UserVariable(new Name('continued')), new NumberLiteral('1'))])]);
        $statement1 = (new Embedding())->statement(new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), $body));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $created = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new ProcedureCall(new QualifiedName(new Name('p'))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertSame(1, $session->variables->user('handled')[0]);
        self::assertSame(1, $session->variables->user('continued')[0]);
    }
}
