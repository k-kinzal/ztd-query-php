<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Depth;
use Fuzz\Structure\Metadata;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Scalar;

/**
 * Depth bounds remove impossible type choices, never SQL failure outcomes.
 */
#[CoversNothing]
#[Medium]
final class DepthTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, int, bool}>
     */
    public static function providerConstructors(): iterable
    {
        yield 'literal at zero' => [NullLiteral::class, 0, true];
        yield 'nothing below zero' => [NullLiteral::class, -1, false];
        yield 'interface with leaf' => [Scalar::class, 0, true];
        yield 'column needs name' => [ColumnUse::class, 0, false];
        yield 'column with name' => [ColumnUse::class, 1, true];
        yield 'select needs item' => [Select::class, 0, false];
        yield 'select with star' => [Select::class, 1, true];
    }

    /**
     * @param class-string $class
     * @throws JsonException When the reviewed inventory is invalid
     */
    #[DataProvider('providerConstructors')]
    public function testConstructorTypesDetermineTheMinimumDepth(string $class, int $depth, bool $expected): void
    {
        self::assertSame($expected, (new Depth(new Catalogue(), new Metadata()))->object($class, $depth));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * @throws JsonException When the reviewed inventory is invalid
     * @throws ReflectionException When a public constructor cannot be invoked
     */
    #[DataProvider('providerSeeds')]
    public function testZeroDepthInterfaceConstructionChoosesOnlyLeaves(string $seed): void
    {
        $construction = new Construction(new Choices('depth:' . $seed), new Catalogue(), new Metadata());
        $node = $construction->object(Scalar::class, 0);

        self::assertInstanceOf(Scalar::class, $node);
        self::assertTrue($construction->depth->object($node::class, 0));
    }
}
