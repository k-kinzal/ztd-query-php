<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Verification\TreeComparison;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

#[CoversClass(TreeComparison::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class TreeComparisonTest extends TestCase
{
    public function testNodesFindsNoDifferenceBetweenTreesOfTheSameSyntax(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $source = $language->parser()->parse('select a from t');
        $copy = $language->parser()->parse('SELECT a FROM t');
        $comparison = new TreeComparison($language->vocabulary(), $language->values()->comments($source), $language->values()->comments($copy));
        self::assertNull($comparison->nodes($source, $copy));
    }

    public function testNodesDescribesTheFirstRuleThatDiffers(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $source = $language->parser()->parse('SELECT (a) FROM t');
        $copy = $language->parser()->parse('SELECT a FROM t');
        $comparison = new TreeComparison($language->vocabulary(), $language->values()->comments($source), $language->values()->comments($copy));
        self::assertStringStartsWith('The rule ', (string) $comparison->nodes($source, $copy));
    }

    public function testTokensAllowsLetterCaseToDifferOnlyForFixedWords(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $source = $language->parser()->parse('select a');
        $copy = $language->parser()->parse('SELECT A');
        $comparison = new TreeComparison($language->vocabulary(), $language->values()->comments($source), $language->values()->comments($copy));
        $sourceTokens = $source->tokens();
        $copyTokens = $copy->tokens();
        self::assertNull($comparison->tokens($sourceTokens[0], $copyTokens[0], true));
        self::assertNotNull($comparison->tokens($sourceTokens[0], $copyTokens[0], false));
        self::assertStringStartsWith('The spelling ', (string) $comparison->tokens($sourceTokens[1], $copyTokens[1], false));
    }

    public function testTokensComparesTheCommentsBeforeEachToken(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $source = $language->parser()->parse('SELECT /* x */ a');
        $copy = $language->parser()->parse('SELECT a');
        $comparison = new TreeComparison($language->vocabulary(), $language->values()->comments($source), $language->values()->comments($copy));
        self::assertStringStartsWith('The comments before ', (string) $comparison->tokens($source->tokens()[1], $copy->tokens()[1], false));
    }

    public function testFixedAnswersWhetherTheModelSpellsATokenAsTheGrammarDoes(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $tree = $language->parser()->parse('SELECT a FROM t');
        $comparison = new TreeComparison($language->vocabulary(), $language->values()->comments($tree), $language->values()->comments($tree));
        $names = $tree->find('nm');
        self::assertNotEmpty($names);
        self::assertFalse($comparison->fixed($names[0], 0));
        $selects = $tree->find('oneselect');
        self::assertNotEmpty($selects);
        self::assertTrue($comparison->fixed($selects[0], 0));
    }
}
