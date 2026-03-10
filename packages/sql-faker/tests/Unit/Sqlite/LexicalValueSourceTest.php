<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\LexicalValueSource;

#[CoversNothing]
final class LexicalValueSourceTest extends TestCase
{
    public function testLexicalValueSourceCanBeImplementedBySqliteCollaborators(): void
    {
        $source = new class () implements LexicalValueSource {
            public function quotedIdentifier(int $minLength = 1, int $maxLength = 128): string
            {
                return '"name"';
            }
            public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
            {
                return "'value'";
            }
            public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
            {
                return '1';
            }
            public function decimalLiteral(int $precision = 15, int $scale = 2): string
            {
                return '1.00';
            }
        };

        self::assertSame('"name"', $source->quotedIdentifier());
        self::assertSame('1', $source->integerLiteral());
    }
}
