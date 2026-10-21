<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Large]
final class GeneratedContextSampleTest extends TestCase
{
    /**
     * @return iterable<int, array{Statement|Scalar}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStructures(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([63, 86, 181, 187, 192, 193, 240] as $index) {
            $choices = new Choices('772df87fbb5897c8ca43d6bd06565da7:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof Statement || $node instanceof Scalar, 'The recorded whole-model roots remain statements or scalars.');
            yield $index => [$node];
        }
    }

    /**
     * @throws JsonException When an observation cannot be encoded
     */
    #[DataProvider('providerStructures')]
    public function testOriginalGeneratedStructuresAgreeAcrossTheirCatalogHistories(Statement|Scalar $node): void
    {
        $observation = (new Comparison())->compare($node, (new Profiles())->select($node));
        self::assertTrue($observation['matches'], json_encode($observation, JSON_THROW_ON_ERROR));
    }
}
