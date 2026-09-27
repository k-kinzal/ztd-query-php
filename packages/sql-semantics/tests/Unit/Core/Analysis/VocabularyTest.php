<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Vocabulary;
use SqlSemantics\Statement\Model\Sqlite\Choice\SortorderChoice_01affc0e as Sortorder;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2 as Integer;

#[CoversClass(Vocabulary::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class VocabularyTest extends TestCase
{
    public function testFromFileLoadsAGeneratedVocabulary(): void
    {
        $vocabulary = Vocabulary::fromFile(dirname(__DIR__, 4) . '/../sql-semantics-sqlite/resources/mapping/sqlite-3.47.2.php');
        self::assertNotNull($vocabulary->recipe('nm', 0));
    }

    public function testTerminalsListWhatALexedTerminalCanStandFor(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        self::assertSame(['INTEGER'], array_slice($vocabulary->terminals('INTEGER'), 0, 1));
        self::assertContains('ID', $vocabulary->terminals('ABORT'));
    }

    public function testRecipeAndFormLocateAlternativesOfARule(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        self::assertSame(['nm', 'DOT', 'nm'], $vocabulary->recipe('fullname', 1)['symbols'] ?? null);
        self::assertNull($vocabulary->recipe('fullname', 9));
        self::assertSame(Name::class, $vocabulary->form('nm', ['idj'])['class'] ?? null);
        self::assertNull($vocabulary->form('nm', ['STRING', 'STRING']));
    }

    public function testFormAnswersNothingForAnUnknownRule(): void
    {
        self::assertNull(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary->form('no_such_rule', ['ID']));
    }

    public function testReachesFollowsForwardingAlternatives(): void
    {
        $reach = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary->reaches('expr');
        self::assertSame('expr', $reach[0]);
        self::assertContains('term', $reach);
        self::assertSame(['nm'], \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary->reaches('nm'));
    }

    public function testLeafFindsTheSingleTerminalFormThroughClassesAndFallbacks(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        self::assertSame(Integer::class, $vocabulary->leaf('expr', 'INTEGER')['class'] ?? null);
        self::assertSame(Name::class, $vocabulary->leaf('nm', 'ID')['class'] ?? null);
        self::assertSame(Name::class, $vocabulary->leaf('nm', 'KEY')['class'] ?? null);
        self::assertNull($vocabulary->leaf('nm', 'SELECT'));
        self::assertContains('ID', $vocabulary->terminals('KEY'));
        self::assertContains('idj', $vocabulary->terminals('KEY'));
        self::assertSame('KEY', $vocabulary->terminals('KEY')[0]);
    }

    public function testBuildConstructsValuesAndChoicesFromRecipes(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        $recipe = $vocabulary->leaf('term', 'INTEGER');
        self::assertNotNull($recipe);
        $integer = $vocabulary->build($recipe, ['7']);
        self::assertInstanceOf(Integer::class, $integer);
        self::assertSame('7', $integer->value);
        $choice = $vocabulary->leaf('sortorder', 'DESC');
        self::assertNotNull($choice);
        self::assertSame(Sortorder::from('DESC'), $vocabulary->build($choice, []));
    }

    public function testShapeAnswersTheRuleAndSymbolsOfAValue(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        self::assertSame(['rule' => 'nm', 'symbols' => ['idj']], $vocabulary->shape(new Name('x')));
        self::assertNull($vocabulary->shape(Sortorder::cases()[0]));
    }

    public function testIsRuleTellsRulesFromTerminals(): void
    {
        $vocabulary = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->platform()->values('sqlite-3.47.2')->vocabulary;
        self::assertTrue($vocabulary->isRule('nm'));
        self::assertFalse($vocabulary->isRule('DOT'));
    }
}
