<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict
 */
final class UpdateConflictTest extends TestCase
{
    public function testInheritedPolicyIsDistinctFromExplicitOverrides(): void
    {
        self::assertSame(UpdateConflict::Inherit, UpdateConflict::from('inherit'));
        self::assertSame(UpdateConflict::Abort, UpdateConflict::from('abort'));
        self::assertSame(UpdateConflict::Ignore, UpdateConflict::from('ignore'));
    }
}
