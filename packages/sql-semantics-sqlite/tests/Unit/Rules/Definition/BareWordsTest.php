<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\BareWords;

#[CoversClass(BareWords::class)]
#[Small]
final class BareWordsTest extends TestCase
{
    public function testUsableAcceptsIdentifiers(): void
    {
        $words = new BareWords();

        self::assertTrue($words->usable('rowid'));
        self::assertTrue($words->usable('_a1$'));
        self::assertTrue($words->usable("caf\xC3\xA9"));
    }

    public function testUsableRefusesTextThatIsNotOneIdentifier(): void
    {
        $words = new BareWords();

        self::assertFalse($words->usable(''));
        self::assertFalse($words->usable('1a'));
        self::assertFalse($words->usable('a b'));
        self::assertFalse($words->usable('a-b'));
        self::assertFalse($words->usable('$a'));
    }

    public function testUsableAcceptsOnlyTheKeywordsThatFallBackToIdentifiers(): void
    {
        $words = new BareWords();

        self::assertTrue($words->usable('key'));
        self::assertTrue($words->usable('GENERATED'));
        self::assertFalse($words->usable('select'));
        self::assertFalse($words->usable('TABLE'));
    }

    public function testAcceptedParsesTheKeywordAtANamePosition(): void
    {
        $words = new BareWords();

        self::assertTrue($words->accepted('ABORT'));
        self::assertFalse($words->accepted('FROM'));
    }
}
