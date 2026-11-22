<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RevokeAll::class)]
#[Medium]
final class RevokeAllTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('REVOKE ALL, GRANT OPTION FROM u')->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS ALL, GRANT OPTION FROM u IGNORE UNKNOWN USER', (new Inspection())->inspect('revoke if exists all privileges, grant option from u ignore unknown user')->toString());
    }
}
