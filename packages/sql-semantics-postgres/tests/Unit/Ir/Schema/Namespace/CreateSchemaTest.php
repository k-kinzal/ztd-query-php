<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateSchema::class)]
final class CreateSchemaTest extends TestCase
{
    public function testToStringPreservesAnExplicitSessionRole(): void
    {
        $schema = new CreateSchema(new Identifier('new."棚"'), SchemaSessionRole::Session, true);

        self::assertSame('CREATE SCHEMA IF NOT EXISTS "new.""棚""" AUTHORIZATION SESSION_USER', $schema->toString());
    }

    public function testToStringDerivesTheNamedOwnerNamespace(): void
    {
        $owner = new Identifier('owner."one"');
        $schema = new CreateSchema(null, $owner);

        self::assertSame($owner, $schema->name);
        self::assertSame('CREATE SCHEMA "owner.""one""" AUTHORIZATION "owner.""one"""', $schema->toString());
    }

    public function testProfileIdIsFixedOnConstruction(): void
    {
        $schema = new CreateSchema(null, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $schema->profileId());
    }

    public function testRejectsAReservedDerivedName(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A declared schema name cannot start with pg_.');

        new CreateSchema(null, new Identifier('pg_checkpoint'));
    }
}
