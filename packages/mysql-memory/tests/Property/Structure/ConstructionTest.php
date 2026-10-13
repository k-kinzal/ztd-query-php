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
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;

/**
 * Exercises the public semantic constructor domain without generating SQL.
 */
#[CoversNothing]
#[Medium]
final class ConstructionTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerOperands(): iterable
    {
        foreach ([NumberLiteral::class, BooleanLiteral::class, NullLiteral::class, Parameter::class] as $class) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $class . ':' . $seed => [$class, (string) $seed];
            }
        }
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     * @param class-string $class
     */
    #[DataProvider('providerOperands')]
    public function testGeneratedOperandExecutesWithoutSqlInput(string $class, string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $literal = $construction->object($class, 4);
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $literal);
        $reply = (new Execution())->run($literal);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(1, $reply->rows);
        self::assertCount(1, $reply->rows[0]);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testSeedReplaysTheSameConstructorChoices(): void
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $first = new Construction(new Choices('replay'), $catalogue, $metadata);
        $second = new Construction(new Choices('replay'), $catalogue, $metadata);

        self::assertEquals($first->object(NumberLiteral::class, 4), $second->object(NumberLiteral::class, 4));
        self::assertSame($first->rejections, $second->rejections);
    }
}
