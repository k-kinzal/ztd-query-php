<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
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
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Numeric condition operands are interpreted and matched without input SQL.
 */
#[CoversNothing]
#[Medium]
final class ErrorCodeProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string, bool}>
     */
    public static function providerCodes(): iterable
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
    #[DataProvider('providerCodes')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesConstructedConditionsAreHandledAndExecutionContinues(string $release, int $number, string $text, bool $hex): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $condition = new ConditionDeclaration(new Name('custom'), new ErrorCode(new Numeral($text, $hex)));
        $handler = new HandlerDeclaration(HandlerAction::Continue, [new ConditionName(new Name('custom'))], new SetVariables([new UserAssignment(new UserVariable(new Name('handled')), new NumberLiteral('1'))]));
        $body = new Block([$condition, $handler], [new Signal(new SqlState(new Text('45000')), [new SignalItem(ConditionItemName::MysqlErrno, new NumberLiteral((string) $number))]), new SetVariables([new UserAssignment(new UserVariable(new Name('continued')), new NumberLiteral('1'))])]);

        $created = $execution->runIn($session, new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), $body));
        $execution->runIn($session, new ProcedureCall(new QualifiedName(new Name('p'))));

        self::assertInstanceOf(Completion::class, $created);
        self::assertSame(1, $session->variables->user('handled')[0]);
        self::assertSame(1, $session->variables->user('continued')[0]);
    }
}
