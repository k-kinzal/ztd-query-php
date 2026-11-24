<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccessLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceAccessLowering::class)]
final class SequenceAccessLoweringTest extends TestCase
{
    public function testLowerSelectsNamedAndSchemaWideOperations(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE ROLE "PUBLIC"', 'CREATE SEQUENCE public.ticket', 'CREATE TEMP SEQUENCE ticket']);
        $grant = $semantics->analyze('GRANT USAGE ON SEQUENCE ticket TO "PUBLIC" GRANTED BY CURRENT_ROLE', $context);
        $revoke = $semantics->analyze('REVOKE SELECT ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC', $context);
        self::assertInstanceOf(GrantSequenceAccess::class, $grant);
        self::assertSame('GRANT USAGE ON SEQUENCE "pg_temp"."ticket" TO "PUBLIC" GRANTED BY CURRENT_USER', $grant->toString());
        self::assertInstanceOf(RevokeSequenceAccess::class, $revoke);
        self::assertSame('REVOKE SELECT ON ALL SEQUENCES IN SCHEMA "public" FROM PUBLIC RESTRICT', $revoke->toString());
    }
}
