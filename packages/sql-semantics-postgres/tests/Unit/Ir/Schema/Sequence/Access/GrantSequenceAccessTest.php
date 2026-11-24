<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(GrantSequenceAccess::class)]
final class GrantSequenceAccessTest extends TestCase
{
    public function testToStringPreservesLegacyOnlyGrantOption(): void
    {
        $target = new NamedSequences([new SequenceSelector(new Identifier('ticket'), new Identifier('public'))]);
        $recipient = new SequenceGrantee(UniversalGrantee::Everyone);
        $legacy = new SequenceAccess($target, [SequenceRight::LegacyRule], [$recipient]);
        self::assertSame('GRANT RULE ON SEQUENCE "public"."ticket" TO PUBLIC WITH GRANT OPTION', (new GrantSequenceAccess($legacy, true))->toString());

        $all = new SequenceAccess($target, [], [$recipient]);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Grant options on named sequences require individual grantees.');
        new GrantSequenceAccess($all, true);
    }

    public function testProfileIdComesFromTheCheckedOperands(): void
    {
        $semantics = new Semantics('pg-16.6');
        $value = $semantics->analyze('GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO PUBLIC');
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
