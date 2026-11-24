<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\GrantSequenceDefaults;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultPolicy;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(GrantSequenceDefaults::class)]
final class GrantSequenceDefaultsTest extends TestCase
{
    public function testToStringRendersAResolvedScopedGrant(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO PUBLIC');
        self::assertSame('ALTER DEFAULT PRIVILEGES IN SCHEMA "public" GRANT USAGE ON SEQUENCES TO PUBLIC', $value->toString());
    }

    public function testProfileIdExposesThePolicyRelease(): void
    {
        $value = (new Semantics('pg-16.6'))->analyze('ALTER DEFAULT PRIVILEGES GRANT USAGE ON SEQUENCES TO PUBLIC');
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testLegacyRightStillRefusesAUniversalGrantOption(): void
    {
        $policy = new SequenceDefaultPolicy([], [], [SequenceRight::LegacyRule], [new SequenceGrantee(UniversalGrantee::Everyone)]);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Default sequence grant options require individual recipients.');
        new GrantSequenceDefaults($policy, true);
    }
}
