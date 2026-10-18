<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Scalar;

use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Term;
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
        $depth = 64;
        $leaf = Term::constant(1);
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $a = $leaf;
        $depth1 = 64;
        $leaf1 = Term::constant(1);
        $leaf1 = array_reduce(array_fill(0, $depth1, null), static fn (Term $leaf1): Term => Term::array([$leaf1, $leaf1]), $leaf1);
        $b = $leaf1;
        $depth2 = 64;
        $leaf2 = Term::constant(2);
        $leaf2 = array_reduce(array_fill(0, $depth2, null), static fn (Term $leaf2): Term => Term::array([$leaf2, $leaf2]), $leaf2);
        $c = $leaf2;
        self::assertSame($identity->key($a), $identity->key($b));
        self::assertNotSame($identity->key($a), $identity->key($c));
    }
    public function testKeyHandlesDeepSharedGraphsInLinearSpace(): void
    {
        $depth = 2000;
        $leaf = Term::constant(1);
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $a = $leaf;
        $depth1 = 2000;
        $leaf1 = Term::constant(1);
        $leaf1 = array_reduce(array_fill(0, $depth1, null), static fn (Term $leaf1): Term => Term::array([$leaf1, $leaf1]), $leaf1);
        $b = $leaf1;
        $identity = new Identity();
        self::assertSame($identity->key($a), $identity->key($b));
    }
}
