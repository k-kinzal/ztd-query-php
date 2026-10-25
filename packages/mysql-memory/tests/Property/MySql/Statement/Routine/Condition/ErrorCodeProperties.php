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
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
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
final class ErrorCodeProperties extends TestCase
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
     * @return iterable<string, array{string, int, string, bool}>
     */
    public static function providerErrorCodeCodes(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                $choices = new Choices('error-code:' . $seed);
                $number = $choices->integer(1, 65535);
                $hex = $seed % 4 === 0;
                $text = $hex ? dechex($number) : str_repeat('0', $choices->integer(0, 6)) . $number . $choices->one(['', '.9', 'e3', '.7e8']);
                yield $release . ':' . $seed => [$release, $number, $text, $hex];
            }
        }
    }

    /**
     * A declared handler consumes the condition and execution continues after SIGNAL.
     */
    #[DataProvider('providerErrorCodeCodes')]
    #[Test]
    public function satisfiesConstructedConditionsAreHandledAndExecutionContinues(string $release, int $number, string $text, bool $hex): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $condition = new ConditionDeclaration(new Name('custom'), new Model(new Numeral($text, $hex)));
        $handler = new HandlerDeclaration(HandlerAction::Continue, [new ConditionName(new Name('custom'))], new SetVariables([new UserAssignment(new UserVariable(new Name('handled')), new NumberLiteral('1'))]));
        $body = new Block([$condition, $handler], [new Signal(new SqlState(new Text('45000')), [new SignalItem(ConditionItemName::MysqlErrno, new NumberLiteral((string) $number))]), new SetVariables([new UserAssignment(new UserVariable(new Name('continued')), new NumberLiteral('1'))])]);
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
