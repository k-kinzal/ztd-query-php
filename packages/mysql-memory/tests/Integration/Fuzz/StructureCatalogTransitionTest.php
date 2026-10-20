<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Large]
final class StructureCatalogTransitionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement|Scalar}>
     */
    public static function providerInputs(): iterable
    {
        $name = new QualifiedName(new Name('catalog.name'));
        $nodes = [
            'alter event' => new M\Routine\AlterEvent($name, completion: M\Routine\Event\Completion::Preserve),
            'show event' => new M\Utility\Show\Program\ShowCreateEvent($name),
            'show view' => new M\Utility\Show\Schema\ShowCreateView(new M\Utility\Show\InspectedTable($name)),
            'match' => new M\Expression\Access\FullTextSearch([new M\Name\ColumnUse(new Name('body'))], new M\Literal\StringLiteral(['missing'])),
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ($nodes as $case => $node) {
                yield $release . ' ' . $case => [$release, $node];
            }
        }
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInputs')]
    public function testCompareRetainsTheRequestAcrossCatalogStates(string $release, M\Statement|Scalar $node): void
    {
        $result = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)), port: 29740);
        self::assertTrue($result['matches'], json_encode($result, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('error', $result['native']);
        self::assertNotEmpty($result['transitions']);
        $last = $result['transitions'][count($result['transitions']) - 1];
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertSame($result['sql'], $last['sql']);
    }
}
