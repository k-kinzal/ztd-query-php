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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
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
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Generated SQLSTATE values can be declared, signalled and handled without input SQL.
 */
#[CoversNothing]
#[Medium]
final class SqlStateProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStates(): iterable
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
    #[DataProvider('providerStates')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesConstructedConditionsAreHandledAndExecutionContinues(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $handler = new HandlerDeclaration(HandlerAction::Continue, [new SqlState(new Text($value))], new SetVariables([new UserAssignment(new UserVariable(new Name('handled')), new NumberLiteral('1'))]));
        $body = new Block([$handler], [new Signal(new SqlState(new Text($value))), new SetVariables([new UserAssignment(new UserVariable(new Name('continued')), new NumberLiteral('1'))])]);

        $created = $execution->runIn($session, new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), $body));
        $execution->runIn($session, new ProcedureCall(new QualifiedName(new Name('p'))));

        self::assertInstanceOf(Completion::class, $created);
        self::assertSame(1, $session->variables->user('handled')[0]);
        self::assertSame(1, $session->variables->user('continued')[0]);
    }
}
