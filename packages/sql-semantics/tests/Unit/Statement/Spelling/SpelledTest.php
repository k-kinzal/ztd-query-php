<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Spelling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(Spelled::class)]
#[Small]
final class SpelledTest extends TestCase
{
    public function testSpellingKeepsTheGapAndTheText(): void
    {
        $token = new Spelled(" /* c */\n", 'and');

        self::assertSame(" /* c */\n", $token->gap);
        self::assertSame('and', $token->text);
    }

    public function testSpellingRefusesAnEmptyToken(): void
    {
        $this->expectExceptionMessage('A spelled token is not empty.');

        new Spelled(' ', '');
    }
}
