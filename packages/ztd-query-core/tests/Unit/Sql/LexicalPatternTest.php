<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalPattern;

#[CoversClass(LexicalPattern::class)]
#[UsesClass(InvalidDefinitionException::class)]
final class LexicalPatternTest extends TestCase
{
    public function testMatchAtAnswersWhatThePatternMatchedThere(): void
    {
        self::assertSame('12', (new LexicalPattern())->matchAt('/^\d+/', 'x12y', 1));
    }

    public function testMatchAtIsNothingWhereThePatternMatchedNoCharacterAtAll(): void
    {
        self::assertNull((new LexicalPattern())->matchAt('/^\d*/', 'abc', 0));
    }

    public function testMatchAtIsNothingWhereThePatternDidNotMatch(): void
    {
        self::assertNull((new LexicalPattern())->matchAt('/^\d+/', 'abc', 0));
    }

    public function testMatchAtIsNothingWhereTheDialectDeclaredNoPattern(): void
    {
        self::assertNull((new LexicalPattern())->matchAt(null, 'abc', 0));
    }

    public function testMatchesCharacterReportsACharacterThePatternAccepts(): void
    {
        self::assertTrue((new LexicalPattern())->matchesCharacter('/^[a-z]$/', 'a'));
    }

    public function testMatchesCharacterIsFalseForNoCharacterAtAll(): void
    {
        self::assertFalse((new LexicalPattern())->matchesCharacter('/^[a-z]$/', ''));
    }

    public function testMatchesCharacterIsFalseForACharacterThePatternRefuses(): void
    {
        self::assertFalse((new LexicalPattern())->matchesCharacter('/^[a-z]$/', '1'));
    }

    public function testAssertValidRefusesAPatternPregWillNotRead(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        (new LexicalPattern())->assertValid('/[/');
    }

    public function testAssertValidRefusesAnEmptyPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        (new LexicalPattern())->assertValid('');
    }

    public function testAssertValidAcceptsThatTheDialectDeclaredNoPattern(): void
    {
        (new LexicalPattern())->assertValid(null);

        self::assertNull((new LexicalPattern())->matchAt(null, '', 0));
    }

    public function testAssertValidLeavesTheErrorHandlerAsItFoundIt(): void
    {
        $handled = false;
        set_error_handler(static function () use (&$handled): bool {
            $handled = true;

            return true;
        });
        try {
            (new LexicalPattern())->assertValid('/^\d+/');
            trigger_error('error handler probe', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        self::assertTrue($handled);
    }
}
