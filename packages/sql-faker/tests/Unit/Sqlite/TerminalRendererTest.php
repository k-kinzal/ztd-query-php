<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Sqlite\LexicalValueGenerator;
use SqlFaker\Sqlite\TerminalRenderer;

#[CoversNothing]
final class TerminalRendererTest extends TestCase
{
    public function testRenderMapsSqliteTerminalNamesToTokens(): void
    {
        $faker = Factory::create();
        $renderer = new TerminalRenderer($faker, new LexicalValueGenerator($faker));

        $tokens = $renderer->render(new TerminalSequence(['ID', 'CONCAT', 'STRING']))->tokens;

        self::assertMatchesRegularExpression('/^_i[0-9a-z]+$/', $tokens[0]);
        self::assertSame('||', $tokens[1]);
        self::assertMatchesRegularExpression("/^'[A-Za-z0-9_]+'$/", $tokens[2]);
    }
}
