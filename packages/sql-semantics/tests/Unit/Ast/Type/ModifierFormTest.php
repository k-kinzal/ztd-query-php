<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Type\ModifierForm;

#[CoversClass(ModifierForm::class)]
final class ModifierFormTest extends TestCase
{
    public function testModifierFormsKeepIdentifiersDistinctFromTextConstants(): void
    {
        self::assertNotSame(ModifierForm::Text, ModifierForm::Identifier);
        self::assertCount(3, ModifierForm::cases());
    }
}
