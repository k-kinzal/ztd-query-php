<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\Replacement;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Builtin\Replacement
 */
#[CoversClass(Replacement::class)]
#[UsesClass(Term::class)]
#[Small]
final class ReplacementTest extends TestCase
{
    public function testApplyCorrelatesReplacementAndCount(): void
    {
        $result = (new Replacement())->apply([Term::fromNative(['ab', 'x']), Term::fromNative(['x', 'z']), Term::fromNative(['first' => 'abab', 'next' => 'x'])]);
        self::assertSame(['result' => ['first' => 'zz', 'next' => 'z'], 'count' => 5], $result->native());
    }
    public function testStringsRejectsAnUnknownArrayRemainder(): void
    {
        self::assertNull((new Replacement())->strings(Term::array(['known' => Term::constant('x')], true)));
    }
    public function testStringsRetainsByteStringsAndKeys(): void
    {
        self::assertSame(['a' => "\xFF\0"], (new Replacement())->strings(Term::fromNative(['a' => "\xFF\0"])));
    }
}
