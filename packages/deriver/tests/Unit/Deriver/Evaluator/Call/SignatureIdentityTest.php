<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\SignatureIdentity;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\SignatureIdentity
 */
#[CoversClass(SignatureIdentity::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Scalar\Identity::class)]
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
