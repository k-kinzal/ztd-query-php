<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Standard\Replacement
 */
#[CoversClass(\Deriver\Standard\Replacement::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ReplacementTest extends TestCase
{
    public function testApplyCorrelatesReplacementAndCount(): void
    {
        $result = (new \Deriver\Standard\Replacement())->apply([\Deriver\Value\Term::fromNative(['ab', 'x']), \Deriver\Value\Term::fromNative(['x', 'z']), \Deriver\Value\Term::fromNative(['first' => 'abab', 'next' => 'x'])]);
        self::assertSame(['result' => ['first' => 'zz', 'next' => 'z'], 'count' => 5], $result->native());
    }
    public function testStringsRejectsAnUnknownArrayRemainder(): void
    {
        self::assertNull((new \Deriver\Standard\Replacement())->strings(\Deriver\Value\Term::array(['known' => \Deriver\Value\Term::constant('x')], true)));
    }
    public function testStringsRetainsByteStringsAndKeys(): void
    {
        self::assertSame(['a' => "\xFF\0"], (new \Deriver\Standard\Replacement())->strings(\Deriver\Value\Term::fromNative(['a' => "\xFF\0"])));
    }
}
