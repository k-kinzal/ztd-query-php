<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Model\Registration\SignatureIdentity;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Registration\SignatureIdentity
 */
#[CoversClass(SignatureIdentity::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(Term::class)]
#[Small]
final class SignatureIdentityTest extends TestCase
{
    public function testKeyIncludesReferenceAndDefaultContracts(): void
    {
        $identity = new SignatureIdentity();
        $a = new Signature([new Parameter('x', default: Term::constant(1))]);
        $b = new Signature([new Parameter('x', default: Term::constant(2))]);
        $c = new Signature($a->parameters, byReference: true);
        self::assertNotSame($identity->key($a), $identity->key($b));
        self::assertNotSame($identity->key($a), $identity->key($c));
    }
}
