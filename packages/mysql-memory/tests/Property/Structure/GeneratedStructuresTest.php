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
use InvalidArgumentException;
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
     * @return iterable<string, array{class-string, int, string}>
     * @throws JsonException When the reviewed type inventory cannot be read
     * @throws InvalidArgumentException When the sample count is not positive
     */
    public static function providerRoots(): iterable
    {
        $seed = getenv('MYSQL_MEMORY_PBT_SEED');
        $seed = $seed === false ? 'all-model-roots' : $seed;
        $requested = getenv('MYSQL_MEMORY_PBT_SAMPLES');
        $samples = $requested === false ? 1 : filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($samples === false) {
            throw new InvalidArgumentException('MYSQL_MEMORY_PBT_SAMPLES must be a positive integer.');
        }
        $roots = (new Catalogue())->roots();
        foreach ($roots as $index => $class) {
            for ($sample = 0; $sample < $samples; $sample++) {
                yield $class . ':' . $sample => [$class, $index + $sample * count($roots), $seed];
            }
        }
    }

    /**
     * @param class-string $class
     * @throws JsonException When the type inventory is malformed
     * @throws ReflectionException When a public constructor cannot be reflected
     */
    #[DataProvider('providerRoots')]
    public function testPublicStructureHasNoUnexplainedExecutionFailure(string $class, int $index, string $seed): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices($seed . ':' . $index);
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertTrue($node instanceof Statement || $node instanceof Scalar);
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        try {
            (new Execution())->run($node, substr($profile->grammar->value, 6), (new Bindings())->nulls($node), $profiles->mode($profile));
        } catch (SqlError $error) {
            self::assertNotNull((new Rejections())->reason($error, $node), $class . ' seed=' . $seed . ' index=' . $index . ': ' . $error->getCode() . ' ' . $error->getMessage());
        }
    }
}
