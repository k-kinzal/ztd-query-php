<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Bindings;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Rejections;
use JsonException;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Samples every public root from its constructor types and consumes it directly.
 * No native comparison or state-transition history is part of this property.
 */
#[CoversNothing]
#[Large]
final class GeneratedStructuresTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, int}>
     * @throws JsonException When the reviewed type inventory cannot be read
     */
    public static function providerRoots(): iterable
    {
        foreach ((new Catalogue())->roots() as $index => $class) {
            yield $class => [$class, $index];
        }
    }

    /**
     * @param class-string $class
     * @throws JsonException When the type inventory is malformed
     * @throws ReflectionException When a public constructor cannot be reflected
     */
    #[DataProvider('providerRoots')]
    public function testPublicStructureHasNoUnexplainedExecutionFailure(string $class, int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('all-model-roots:' . $index);
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertTrue($node instanceof Statement || $node instanceof Scalar);
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        try {
            (new Execution())->run($node, substr($profile->grammar->value, 6), (new Bindings())->nulls($node), $profiles->mode($profile));
        } catch (SqlError $error) {
            self::assertNotNull((new Rejections())->reason($error, $node), $class . ': ' . $error->getCode() . ' ' . $error->getMessage());
        }
    }
}
