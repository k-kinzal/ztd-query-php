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
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversNothing]
#[Large]
final class StorageCatalogHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement, ?int}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStructures(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([141, 157, 159, 162, 168, 169] as $index) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof M\Statement, 'The recorded whole-model root retains its statement identity.');
            $initial = substr((new Profiles())->select($node)->grammar->value, 6);
            foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
                yield $release . ':' . $index => [$release, $node, $index === 159 ? ($release === '8.0.44' ? 3642 : 1149) : null];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testCatalogSetupPreservesTheGeneratedRequestAndEveryLaterCondition(string $release, M\Statement $node, ?int $laterError): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)));
        self::assertTrue($observation['matches'], serialize($observation));
        self::assertNotEmpty($observation['transitions']);
        $last = $observation['transitions'][count($observation['transitions']) - 1];
        self::assertSame($observation['sql'], $last['sql']);
        $condition = $last['native']['error'] ?? [];
        self::assertIsArray($condition);
        self::assertSame($laterError, $condition[0] ?? null);
    }
}
