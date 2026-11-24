<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Access\SchemaGrantLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaGrantLowering::class)]
final class SchemaGrantLoweringTest extends TestCase
{
    public function testLowerBindsTheFirstActualLocalSequenceAndKeepsRoles(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.feed', 'CREATE ROLE "PUBLIC"', 'CREATE ROLE account_owner']);
        $parent = $semantics->analyze(
            'CREATE SCHEMA archive AUTHORIZATION account_owner'
            . ' GRANT SELECT ON SEQUENCE feed TO SESSION_USER'
            . ' CREATE SEQUENCE feed'
            . ' GRANT UPDATE ON SEQUENCE public.feed TO "PUBLIC" WITH GRANT OPTION GRANTED BY CURRENT_ROLE',
            $context
        );

        self::assertInstanceOf(CreateSchema::class, $parent);
        self::assertSame(
            ['GRANT SELECT ON SEQUENCE "archive"."feed" TO SESSION_USER',
                'GRANT UPDATE ON SEQUENCE "public"."feed" TO "PUBLIC" WITH GRANT OPTION GRANTED BY CURRENT_USER'],
            array_map(static fn ($grant) => $grant->toString(), $parent->grants)
        );
        self::assertStringContainsString('NO CYCLE GRANT SELECT ON SEQUENCE "archive"."feed"', $parent->toString());
    }

    public function testLowerRefusesToSkipALocalIndexInFavorOfAnExternalSequence(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.feed']);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The selected relation is not a sequence.');

        $semantics->analyze(
            'CREATE SCHEMA archive GRANT SELECT ON SEQUENCE feed TO CURRENT_USER'
            . ' CREATE INDEX feed ON ledger (id) CREATE TABLE ledger (id integer)',
            $context
        );
    }
}
