<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\PostgreSql\LexicalValueGenerator;
use SqlFaker\PostgreSql\TerminalRenderer;

#[CoversNothing]
final class TerminalRendererTest extends TestCase
{
    public function testRenderMapsPostgreSqlTerminalNamesToTokens(): void
    {
        $faker = Factory::create();
        $random = new FakerRandomSource($faker);
        $renderer = new TerminalRenderer($random, new LexicalValueGenerator($random));

        $tokens = $renderer->render(new TerminalSequence(['IDENT', 'TYPECAST', 'XCONST']))->tokens;

        self::assertMatchesRegularExpression('/^_i[0-9a-z]+$/', $tokens[0]);
        self::assertSame('::', $tokens[1]);
        self::assertMatchesRegularExpression("/^X'[0-9a-f]+'$/", $tokens[2]);
    }
}
