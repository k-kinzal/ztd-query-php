<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTableField;
use SqlSemantics\Model\Query\Inspection\Field\Schema\CharacterSetField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

#[CoversClass(TextField::class)]
#[Medium]
final class TextFieldTest extends TestCase
{
    public function testLabelIsTheEnumerationValue(): void
    {
        self::assertSame('Create Table', CreateTableField::Definition->label());
        self::assertSame('Default collation', CharacterSetField::DefaultCollation->label());
    }

    public function testTypeDefaultsToTextUnlessAFieldDeclaresOtherwise(): void
    {
        self::assertSame('varchar', CreateTableField::Definition->type());
        self::assertSame('bigint', CharacterSetField::MaximumLength->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, CreateTableField::Definition->nullability());
        self::assertSame(Nullability::NotNull, CharacterSetField::Description->nullability());
    }
}
