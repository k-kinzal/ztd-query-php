<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Sqlite\GenerationContext;

#[CoversClass(GenerationContext::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCatalog::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCoverageCheck::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalProfileSource::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalWitnessCheck::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalWitnessShape::class)]
#[UsesClass(\SqlFaker\Grammar\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTerminalRealizer::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
final class GenerationContextTest extends TestCase
{
    public function testGrammarAndLexicalReleaseAreBoundTogether(): void
    {
        $grammar = new Grammar('start_entry', []);
        $context = new GenerationContext($grammar, Factory::create());

        self::assertSame('cmd', $context->grammar->startSymbol);
        self::assertSame([], $context->grammar->ruleMap);
        self::assertNotSame('', $context->lexicalGrammar->version());
        self::assertNull($context->startSymbol);
        self::assertNull($context->normalize);
    }
}
