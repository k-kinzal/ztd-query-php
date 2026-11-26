<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget;

#[CoversClass(DropSchema::class)]
final class DropSchemaTest extends TestCase
{
    public function testToStringPreservesTheOrderedRepeatedSelectorsAndPolicies(): void
    {
        $existing = new SchemaDropTarget(new Identifier('old."region"'), true);
        $missing = new SchemaDropTarget(new Identifier('not there'), false);
        $value = new DropSchema([$existing, $missing, $missing], ifExists: true, cascade: true);

        self::assertSame([$existing, $missing, $missing], $value->targets);
        self::assertSame('DROP SCHEMA IF EXISTS "old.""region""", "not there", "not there" CASCADE', $value->toString());
    }

    public function testProfileId(): void
    {
        $target = new SchemaDropTarget(new Identifier('app'), true);

        self::assertSame('pg-17.2/utf8/standard-conforming-strings/native', (new DropSchema([$target]))->profileId());
    }

    public function testRejectsContradictoryPresenceForTheSameName(): void
    {
        $name = new Identifier('app');

        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('cannot disagree about a named target');

        new DropSchema([new SchemaDropTarget($name, true), new SchemaDropTarget($name, false)], ifExists: true);
    }
}
