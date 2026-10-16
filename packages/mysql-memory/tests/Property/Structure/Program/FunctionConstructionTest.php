<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;

/**
 * Replays nested constructor search failures without changing the selected root.
 */
#[CoversNothing]
#[Medium]
final class FunctionConstructionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($index = 0; $index < 20; $index++) {
            yield 'body-domain:' . $index => ['body-domain:' . $index];
        }
    }

    /**
     * All descendants share the existing cap; accepted declarations are never discarded.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be inspected
     */
    #[DataProvider('providerSeeds')]
    public function testFunctionRootCanSatisfyItsLanguageAndReturnConstraints(string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $node = $construction->object(CreateFunction::class, 4);
        self::assertInstanceOf(CreateFunction::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }
}
