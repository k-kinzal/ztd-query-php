<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\LexicalValueSource;

#[CoversNothing]
final class LexicalValueSourceTest extends TestCase
{
    public function testLexicalValueSourceCanBeImplementedByProductionCollaborators(): void
    {
        $source = new class () implements LexicalValueSource {
            public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string
            {
                return '`name`';
            }
            public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
            {
                return "'value'";
            }
            public function nationalStringLiteral(int $minLength = 1, int $maxLength = 32): string
            {
                return "N'value'";
            }
            public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string
            {
                return '$$value$$';
            }
            public function integerLiteral(int $min = 1, int $max = 2147483647): string
            {
                return '1';
            }
            public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string
            {
                return '1';
            }
            public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
            {
                return '1';
            }
            public function decimalLiteral(int $precision = 10, int $scale = 2): string
            {
                return '1.00';
            }
            public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string
            {
                return '1.00e0';
            }
            public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
            {
                return '0x1';
            }
            public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
            {
                return '0b1';
            }
            public function hostname(int $minParts = 1, int $maxParts = 1, int $maxPartLength = 16): string
            {
                return 'host';
            }
            public function filterWildcardPattern(int $maxPartLength = 12): string
            {
                return "'db.tbl'";
            }
            public function resetMasterIndex(): string
            {
                return '1';
            }
        };

        self::assertSame('`name`', $source->quotedIdentifier());
        self::assertSame("'db.tbl'", $source->filterWildcardPattern());
    }
}
