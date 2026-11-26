<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\RevokeSequenceDefaults;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RevokeSequenceDefaults::class)]
final class RevokeSequenceDefaultsTest extends TestCase
{
    public function testToStringPreservesTheOptionOnlyModifierAndBehavior(): void
    {
        $value = (new Semantics())->analyze('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR SELECT ON SEQUENCES FROM PUBLIC CASCADE');
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR SELECT ON SEQUENCES FROM PUBLIC CASCADE', $value->toString());
    }

    public function testProfileIdExposesThePolicyRelease(): void
    {
        $value = (new Semantics('pg-16.6'))->analyze('ALTER DEFAULT PRIVILEGES REVOKE USAGE ON SEQUENCES FROM PUBLIC');
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
