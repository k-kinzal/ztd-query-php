<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\FunctionBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;

#[CoversClass(FunctionBody::class)]
#[Small]
final class FunctionBodyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function providerInstructionShapes(): iterable
    {
        yield 'RETURN' => [ReturnStatement::class, true];
        yield 'compound body' => [Block::class, true];
        yield 'cursor instruction' => [OpenCursor::class, false];
    }

    #[DataProvider('providerInstructionShapes')]
    public function testDeriveProgramBelongsToReturnBearingInstructionShapes(string $class, bool $expected): void
    {
        self::assertSame($expected, is_subclass_of($class, FunctionBody::class));
    }
}
