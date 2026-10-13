<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Scalar;

/**
 * Composed negation spellings execute directly from their semantic constructors.
 */
#[CoversNothing]
#[Medium]
final class NegationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTrials(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 25; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When a constructed negation cannot execute
     */
    #[DataProvider('providerTrials')]
    public function testNegationCompositionsExecuteWithoutSourceSql(string $release, string $seed): void
    {
        $choices = new Choices('negation-structures:' . $seed);
        $value = $choices->one([null, 0, 1, 7, 65535]);
        $operators = array_map(static fn (int $position): UnaryOperator => $choices->one([UnaryOperator::Not, UnaryOperator::PairedNot]), range(1, $choices->integer(1, 6)));
        $expression = array_reduce($operators, static fn (Scalar $operand, UnaryOperator $operator): Unary => new Unary($operator, $operand), $value === null ? new NullLiteral() : new NumberLiteral((string) $value));
        $reply = (new Execution())->run($expression, $release);
        $expected = $value === null ? null : (string) (int) (count($operators) % 2 === 0 ? $value !== 0 : $value === 0);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$expected]], $reply->rows);
        self::assertSame(str_starts_with($release, '5.') ? 0 : count($operators), $reply->warnings);
    }
}
