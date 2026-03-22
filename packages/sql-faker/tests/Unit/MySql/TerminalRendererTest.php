<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\MySql\LexicalValueGenerator;
use SqlFaker\MySql\TerminalRenderer;

#[CoversNothing]
final class TerminalRendererTest extends TestCase
{
    public function testRenderMapsTerminalNamesToTokens(): void
    {
        $faker = Factory::create();
        $random = new FakerRandomSource($faker);
        $renderer = new TerminalRenderer($random, new LexicalValueGenerator($random));

        $tokens = $renderer->render(new TerminalSequence(['IDENT', 'EQ', 'NUM']))->tokens;

        self::assertMatchesRegularExpression('/^_i[0-9a-z]+$/', $tokens[0]);
        self::assertSame('=', $tokens[1]);
        self::assertMatchesRegularExpression('/^[0-9]+$/', $tokens[2]);
    }
}
