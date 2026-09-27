<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Identity;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identity::class)]
#[UsesClass(Term::class)]
#[Small]
final class IdentityTest extends TestCase
{
    public function testKeyPreservesTheSemanticContract(): void
    {
        $identity = new Identity();
        self::assertNotSame($identity->key(Term::constant(1)), $identity->key(Term::constant('1')));
        self::assertNotSame($identity->key(Term::constant(0.0)), $identity->key(Term::constant(-0.0)));
        self::assertSame($identity->key(Term::fromNative(['a' => 1])), $identity->key(Term::fromNative(['a' => 1])));
    }
    public function testScalarDistinguishesNegativeZero(): void
    {
        $identity = new Identity();
        self::assertNotSame($identity->scalar(0.0), $identity->scalar(-0.0));
        self::assertNotSame($identity->scalar(1), $identity->scalar('1'));
    }

    public function testKeyPreservesSharedDagComplexityAndContentIdentity(): void
    {
        $identity = new Identity();
        $a = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $b = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $c = \Tests\Fake\ValueDocument::shared(64, Term::constant(2));
        self::assertSame($identity->key($a), $identity->key($b));
        self::assertNotSame($identity->key($a), $identity->key($c));
    }
    public function testKeyHandlesDeepSharedGraphsInLinearSpace(): void
    {
        $a = \Tests\Fake\ValueDocument::shared(2000, Term::constant(1));
        $b = \Tests\Fake\ValueDocument::shared(2000, Term::constant(1));
        $identity = new Identity();
        self::assertSame($identity->key($a), $identity->key($b));
    }
}
