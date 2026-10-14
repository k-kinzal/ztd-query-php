<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastAtLocal;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Typed grouping preserves the completion boundary of a refused BINARY operand.
 */
#[CoversNothing]
#[Medium]
final class BinaryOperandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, bool}>
     */
    public static function providerOperands(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                foreach ([false, true] as $arithmetic) {
                    yield $release . ':' . $seed . ':' . (int) $arithmetic => [$release, $seed, $arithmetic];
                }
            }
        }
    }

    /**
     * Submits generated objects directly and checks their error and warning boundary.
     */
    #[DataProvider('providerOperands')]
    public function testCompletionUsesTheRetainedOperandStructure(string $release, int $seed, bool $arithmetic): void
    {
        $choices = new Choices('binary-operand:' . $seed);
        $operand = new CastAtLocal(new NumberLiteral((string) $choices->integer(0, 10000)), new CastTarget(CastKind::Signed));
        if ($arithmetic) {
            $operand = new Grouped(new Arithmetic(ArithmeticOperator::Plus, $operand, new NumberLiteral('1')));
        }
        for ($depth = $choices->integer(0, 12); $depth > 0; $depth--) {
            $operand = new Grouped($operand);
        }
        $query = new Select([], [new SelectExpression(new BinaryCast($operand)), new SelectExpression(new BinaryCast(new NumberLiteral('2')))]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        try {
            (new Execution())->runIn($session, $query);
            self::fail('The unsupported operand must retain its SQL error.');
        } catch (SqlError $error) {
            self::assertSame(1235, $error->getCode());
            self::assertSame($arithmetic || $release === '8.0.44' ? [1235, 1287, 1287] : [1235, 1287], array_column($session->diagnostics->conditions, 1));
        }
    }
}
