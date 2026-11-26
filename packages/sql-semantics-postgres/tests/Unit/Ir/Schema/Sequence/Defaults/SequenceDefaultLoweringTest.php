<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\RevokeSequenceDefaults;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceDefaultLowering::class)]
final class SequenceDefaultLoweringTest extends TestCase
{
    public function testLowerTreatsAnOmittedScopeAsTheGlobalEffectivePolicy(): void
    {
        $value = (new Semantics())->analyze('ALTER DEFAULT PRIVILEGES REVOKE ALL ON SEQUENCES FROM SESSION_USER');
        self::assertInstanceOf(RevokeSequenceDefaults::class, $value);
        self::assertSame([], $value->policy->creators);
        self::assertSame([], $value->policy->schemas);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE ALL ON SEQUENCES FROM SESSION_USER RESTRICT', $value->toString());
    }
}
