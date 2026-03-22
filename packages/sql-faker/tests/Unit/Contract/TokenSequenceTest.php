<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TokenSequence;

#[CoversNothing]
final class TokenSequenceTest extends TestCase
{
    public function testConstructorStoresTokenList(): void
    {
        self::assertSame(['SELECT', '1'], (new TokenSequence(['SELECT', '1']))->tokens);
    }

    public function testConstructorRejectsInvalidTokenEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TokenSequence(['']);
    }
}
