<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;

#[CoversClass(Declaration::class)]
#[Small]
final class DeclarationTest extends TestCase
{
    public function testImplementationsAreTheFourDeclarations(): void
    {
        self::assertTrue(is_subclass_of(VariableDeclaration::class, Declaration::class));
        self::assertTrue(is_subclass_of(ConditionDeclaration::class, Declaration::class));
        self::assertTrue(is_subclass_of(CursorDeclaration::class, Declaration::class));
        self::assertTrue(is_subclass_of(HandlerDeclaration::class, Declaration::class));
        self::assertFalse(is_subclass_of(OpenCursor::class, Declaration::class));
        self::assertFalse(is_subclass_of(Block::class, Declaration::class));
    }
}
