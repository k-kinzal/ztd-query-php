<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Profiles;

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

/**
 * Constructor-valid samples retain at least one supported language interpretation.
 * No profile failure is discarded; SQL Faker fuzzing checks native execution separately.
 */
#[CoversNothing]
#[Medium]
final class CompositionTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function providerTrials(): iterable
    {
        foreach ([699, 1994, 5420, 7950, 7974, 8332, 9946, 10282, 10875] as $index) {
            yield 'row-roles-20261017:' . $index => [$index];
        }
    }

    /**
     * @throws JsonException When the public constructor inventory is invalid
     * @throws ReflectionException When a public constructor cannot be invoked
     */
    #[DataProvider('providerTrials')]
    public function testAcceptedCompositionHasAPreservingLanguageProfile(int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('row-roles-20261017:' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $node);
        $profile = (new Profiles())->select($node);
        self::assertSame('mysql', $profile->grammar->database());
    }
}
