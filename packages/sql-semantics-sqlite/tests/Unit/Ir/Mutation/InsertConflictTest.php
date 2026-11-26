<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict
 */
final class InsertConflictTest extends TestCase
{
    public function testInheritedPolicyIsDistinctFromBothOverrides(): void
    {
        self::assertSame(InsertConflict::Inherit, InsertConflict::from('inherit'));
        self::assertSame(InsertConflict::Abort, InsertConflict::from('abort'));
        self::assertSame(InsertConflict::Ignore, InsertConflict::from('ignore'));
    }
}
