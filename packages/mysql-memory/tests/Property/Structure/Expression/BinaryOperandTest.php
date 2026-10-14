<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
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
 * Typed grouping preserves values and deprecations of generated BINARY operands.
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
     * Submits generated objects directly and checks values and ordered warnings.
     */
    #[DataProvider('providerOperands')]
    public function testCompletionUsesTheRetainedOperandStructure(string $release, int $seed, bool $arithmetic): void
    {
        $choices = new Choices('binary-operand:' . $seed);
        $value = $choices->integer(0, 10000);
        $operand = new Cast(new NumberLiteral((string) $value), new CastTarget(CastKind::Signed));
        if ($arithmetic) {
            $operand = new Grouped(new Arithmetic(ArithmeticOperator::Plus, $operand, new NumberLiteral('1')));
        }
        for ($depth = $choices->integer(0, 12); $depth > 0; $depth--) {
            $operand = new Grouped($operand);
        }
        $query = new Select([], [new SelectExpression(new BinaryCast($operand)), new SelectExpression(new BinaryCast(new NumberLiteral('2')))]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $reply = (new Execution())->runIn($session, $query);

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $reply);
        self::assertSame([[(string) ($value + ($arithmetic ? 1 : 0)), '2']], $reply->rows);
        self::assertSame([1287, 1287], array_column($session->diagnostics->conditions, 1));
    }
}
