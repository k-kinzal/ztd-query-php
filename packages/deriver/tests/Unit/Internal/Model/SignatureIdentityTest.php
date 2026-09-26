<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\SignatureIdentity
 */
#[CoversClass(\Deriver\Internal\Model\SignatureIdentity::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SignatureIdentityTest extends TestCase
{
    public function testKeyIncludesReferenceAndDefaultContracts(): void
    {
        $identity = new \Deriver\Internal\Model\SignatureIdentity();
        $a = new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('x', default: \Deriver\Value\Term::constant(1))]);
        $b = new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('x', default: \Deriver\Value\Term::constant(2))]);
        $c = new \Deriver\Model\Signature\Signature($a->parameters, byReference: true);
        self::assertNotSame($identity->key($a), $identity->key($b));
        self::assertNotSame($identity->key($a), $identity->key($c));
    }
}
