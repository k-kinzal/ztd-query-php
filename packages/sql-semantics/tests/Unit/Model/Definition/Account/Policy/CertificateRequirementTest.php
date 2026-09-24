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
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CertificateRequirement::class)]
#[Medium]
final class CertificateRequirementTest extends TestCase
{
    public function testKeepsTheConstraintTextAsSupplied(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'/CN=client'", 0));
        self::assertInstanceOf(Literal::class, $value);
        $requirement = new CertificateRequirement(CertificateAttribute::Subject, $value);
        self::assertSame(CertificateAttribute::Subject, $requirement->attribute);
        self::assertSame("'/CN=client'", $requirement->value->text);
    }

    public function testRejectsANonTextConstraint(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '1', 0));
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        new CertificateRequirement(CertificateAttribute::Cipher, $value);
    }
}
