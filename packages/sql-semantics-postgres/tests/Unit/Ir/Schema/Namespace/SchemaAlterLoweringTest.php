<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaAlterLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaAlterLowering::class)]
final class SchemaAlterLoweringTest extends TestCase
{
    public function testLowerPublishesTheTwoNamespaceChangeRoles(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SCHEMA current_scope']);
        [$rename, $owner] = $semantics->analyzeAll(
            'ALTER SCHEMA current_scope RENAME TO backup; ALTER SCHEMA current_scope OWNER TO SESSION_USER;',
            $context,
        );

        self::assertInstanceOf(RenameSchema::class, $rename);
        self::assertInstanceOf(ChangeSchemaOwner::class, $owner);
        self::assertSame('backup', $rename->destination->value);
        self::assertSame(SchemaSessionRole::Session, $owner->owner);
    }
}
