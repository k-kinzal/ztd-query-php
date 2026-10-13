<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Scalar;

/**
 * Constructor invariants distinguish node occurrences from equal immutable values.
 */
#[CoversNothing]
#[Medium]
final class OccurrenceTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerOperands(): iterable
    {
        foreach ([NumberLiteral::class, BooleanLiteral::class, NullLiteral::class, Parameter::class] as $class) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $class . ':' . $seed => [$class, 'occurrence:' . $seed];
            }
        }
    }

    /**
     * @param class-string $class The constructor domain to sample
     * @throws JsonException When the semantic inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    #[DataProvider('providerOperands')]
    public function testSharedGeneratedOperandIsRejectedByTheStatementConstructor(string $class, string $seed): void
    {
        $operand = (new Construction(new Choices($seed), new Catalogue(), new Metadata()))->object($class, 4);
        self::assertInstanceOf(Scalar::class, $operand);

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        new Select([], [new SelectExpression(new Grouped($operand)), new SelectExpression(new Grouped($operand))]);
    }

    /**
     * @param class-string $class The constructor domain to sample
     * @throws JsonException When the semantic inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    #[DataProvider('providerOperands')]
    public function testSeparatelyConstructedEqualOperandsExecuteDirectly(string $class, string $seed): void
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $left = (new Construction(new Choices($seed), $catalogue, $metadata))->object($class, 4);
        $right = (new Construction(new Choices($seed), $catalogue, $metadata))->object($class, 4);
        self::assertInstanceOf(Scalar::class, $left);
        self::assertInstanceOf(Scalar::class, $right);
        self::assertNotSame($left, $right);
        self::assertEquals($left, $right);

        $reply = (new Execution())->run(new Select([], [new SelectExpression(new Grouped($left)), new SelectExpression(new Grouped($right))]));

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(1, $reply->rows);
        self::assertCount(2, $reply->rows[0]);
        self::assertSame($reply->rows[0][0], $reply->rows[0][1]);
    }
}
