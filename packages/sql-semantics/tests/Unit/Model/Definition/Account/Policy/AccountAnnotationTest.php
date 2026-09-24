<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(AccountAnnotation::class)]
#[Medium]
final class AccountAnnotationTest extends TestCase
{
    public function testKeepsTheAttributeDocumentSpelling(): void
    {
        $text = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'{\"team\": 1}'", 0));
        self::assertInstanceOf(Literal::class, $text);
        $annotation = new AccountAnnotation(AnnotationForm::Attribute, $text);
        self::assertSame(AnnotationForm::Attribute, $annotation->form);
        self::assertSame("'{\"team\": 1}'", $annotation->text->text);
    }

    public function testRejectsANumericComment(): void
    {
        $number = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '5', 0));
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new AccountAnnotation(AnnotationForm::Comment, $number);
    }
}
