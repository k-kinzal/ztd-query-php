<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DefinitionOption::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinitionOptionTest extends TestCase
{
    public function testKeepsATypedArgumentOrNone(): void
    {
        $name = new QualifiedName(['eqsel']);
        self::assertSame($name, (new DefinitionOption(OperatorAttribute::Restrict, $name))->value);
        self::assertNull((new DefinitionOption(OperatorAttribute::Restrict, null))->value);
    }

    public function testRejectsAnArgumentOfAnotherForm(): void
    {
        $this->expectException(InvalidStructure::class);
        new DefinitionOption(OperatorAttribute::LeftArg, new QualifiedName(['integer']));
    }
}
