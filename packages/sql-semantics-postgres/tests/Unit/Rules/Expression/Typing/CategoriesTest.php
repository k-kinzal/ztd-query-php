<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Categories::class)]
#[Small]
final class CategoriesTest extends TestCase
{
    public function testBuiltinAnswersTheBaseType(): void
    {
        self::assertSame([Builtin::Varchar, Builtin::Unknown], [(new Categories())->builtin(new Known(new Parameterized(Builtin::Varchar, 4))), (new Categories())->builtin(new NullOnly())]);
    }

    public function testCategoryAnswersTheLetter(): void
    {
        self::assertSame(['N', 'S', 'U'], [(new Categories())->category(Builtin::Int2), (new Categories())->category(Builtin::Text), (new Categories())->category(Builtin::Uuid)]);
    }

    public function testPreferredTellsThePreferredTypes(): void
    {
        self::assertSame([true, false], [(new Categories())->preferred(Builtin::Float8), (new Categories())->preferred(Builtin::Numeric)]);
    }

    public function testImplicitFollowsTheCastDirection(): void
    {
        self::assertSame([true, false], [(new Categories())->implicit(Builtin::Int4, Builtin::Numeric), (new Categories())->implicit(Builtin::Numeric, Builtin::Int4)]);
    }

    public function testNumericTellsTheNumberTypes(): void
    {
        self::assertSame([true, false], [(new Categories())->numeric(Builtin::Float4), (new Categories())->numeric(Builtin::Money)]);
    }

    public function testTextualTellsTheStringTypes(): void
    {
        self::assertSame([true, false], [(new Categories())->textual(Builtin::Name), (new Categories())->textual(Builtin::Bytea)]);
    }
}
