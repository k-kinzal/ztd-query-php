<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\MySql\GenerationContext;

#[CoversClass(GenerationContext::class)]
final class GenerationContextTest extends TestCase
{
    public function testGrammarAndLexicalReleaseAreBoundTogether(): void
    {
        $grammar = new Grammar('start_entry', []);
        $context = new GenerationContext($grammar, Factory::create());

        self::assertSame('start_entry', $context->grammar->startSymbol);
        self::assertSame([], $context->grammar->ruleMap);
        self::assertNotSame('', $context->lexicalGrammar->version());
        self::assertNotNull($context->startSymbol);
        self::assertNotNull($context->normalize);
    }
}
