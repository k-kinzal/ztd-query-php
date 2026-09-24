<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirement;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CertificateRequirements::class)]
#[Medium]
final class CertificateRequirementsTest extends TestCase
{
    public function testKeepsDistinctAttributesInRequestOrder(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'x'", 0));
        self::assertInstanceOf(Literal::class, $value);
        $requirements = new CertificateRequirements([new CertificateRequirement(CertificateAttribute::Issuer, $value), new CertificateRequirement(CertificateAttribute::Subject, $value)]);
        self::assertSame([CertificateAttribute::Issuer, CertificateAttribute::Subject], array_column($requirements->requirements, 'attribute'));
    }

    public function testRejectsARepeatedAttribute(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'x'", 0));
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        new CertificateRequirements([new CertificateRequirement(CertificateAttribute::Cipher, $value), new CertificateRequirement(CertificateAttribute::Cipher, $value)]);
    }
}
