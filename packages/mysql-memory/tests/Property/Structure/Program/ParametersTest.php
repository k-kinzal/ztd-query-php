<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generated parameter lists execute without a source SQL or an input parse tree.
 */
#[CoversNothing]
#[Medium]
final class ParametersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Parameter>, list<NumberLiteral>}>
     */
    public static function providerParameters(): iterable
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
    public static function providerLegacyParameters(): iterable
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
    #[DataProvider('providerParameters')]
    #[DataProvider('providerLegacyParameters')]
    public function testParameterListsBindEveryArgumentToItsDeclaredPosition(string $release, array $parameters, array $arguments): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $name = new QualifiedName(new Name('p'));
        $body = new QueryRoot(new Select([], array_map(static fn (Parameter $parameter): SelectExpression => new SelectExpression(new ColumnUse($parameter->name)), $parameters)));

        $created = $execution->runIn($session, new CreateProcedure($name, new ParameterList($parameters), $body));
        $called = $execution->runIn($session, new ProcedureCall($name, $arguments));

        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([array_map(static fn (NumberLiteral $value): string => $value->text, $arguments)], $called->replies[0]->rows);
    }
}
