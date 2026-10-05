<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('providerImplementationsAreTheFourDeclarations')]
    public function testImplementationsAreTheFourDeclarations(string $class, bool $expected): void
    {
        self::assertSame($expected, is_subclass_of($class, Declaration::class));
    }

    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function providerImplementationsAreTheFourDeclarations(): iterable
    {
        yield 'a variable' => [VariableDeclaration::class, true];
        yield 'a condition' => [ConditionDeclaration::class, true];
        yield 'a cursor' => [CursorDeclaration::class, true];
        yield 'a handler' => [HandlerDeclaration::class, true];
        yield 'OPEN' => [OpenCursor::class, false];
        yield 'a block' => [Block::class, false];
    }
}
