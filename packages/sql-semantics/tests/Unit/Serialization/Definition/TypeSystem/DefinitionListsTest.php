<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Serialization\Definition\TypeSystem\DefinitionLists;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DefinitionLists::class)]
final class DefinitionListsTest extends TestCase
{
    public function testWriteParenthesizesTheElements(): void
    {
        self::assertSame('(FUNCTION = "f", RIGHTARG = integer)', DefinitionLists::write([new DefinitionOption(OperatorAttribute::Function, new QualifiedName(['f'])), new DefinitionOption(OperatorAttribute::RightArg, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))])->toString());
    }

    public function testOptionSpellsEachArgumentForm(): void
    {
        self::assertSame('JOIN = NONE', DefinitionLists::option(new DefinitionOption(OperatorAttribute::Join, null))->toString());
        self::assertSame('HASHES = FALSE', DefinitionLists::option(new DefinitionOption(OperatorAttribute::Hashes, false))->toString());
        self::assertSame('NEGATOR = <>', DefinitionLists::option(new DefinitionOption(OperatorAttribute::Negator, new QualifiedName(['<>'])))->toString());
    }

    public function testOperatorQualifiesThroughTheOperatorSyntax(): void
    {
        self::assertSame('OPERATOR("s".<>)', DefinitionLists::operator(new QualifiedName(['s', '<>']))->toString());
        self::assertSame('<>', DefinitionLists::operator(new QualifiedName(['<>']))->toString());
    }
}
