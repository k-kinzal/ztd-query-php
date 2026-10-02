<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;
use SqlSemantics\Statement\Validation\ValueState;
use stdClass;

#[CoversClass(ValueState::class)]
#[Small]
final class ValueStateTest extends TestCase
{
    public function testChildrenRequiresInitializedReadonlyState(): void
    {
        $state = new ValueState();
        self::assertSame([Quote::None], $state->children(new Name('valid')));
        self::assertNull($state->children(new stdClass()));
    }

    public function testExternalArrayReferencesAreRejectedAtThePublicBoundary(): void
    {
        $member = new StringLiteral('initial');
        $members = [&$member];
        $this->expectException(InvalidConstruction::class);
        new TypeDescriptor(Builtin::Enum, members: $members);
    }
}
