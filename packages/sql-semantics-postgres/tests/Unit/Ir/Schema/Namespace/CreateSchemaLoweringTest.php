<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateSchemaLowering::class)]
final class CreateSchemaLoweringTest extends TestCase
{
    public function testLowerBindsOneDeclaredOwner(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE USER regional', 'CREATE GROUP "Regional"']);
        $schema = $semantics->analyze('CREATE SCHEMA AUTHORIZATION "Regional"', $context);

        self::assertInstanceOf(CreateSchema::class, $schema);
        self::assertInstanceOf(Identifier::class, $schema->owner);
        self::assertSame('Regional', $schema->owner->value);
        self::assertSame('CREATE SCHEMA "Regional" AUTHORIZATION "Regional"', $schema->toString());
    }

    public function testLowerRequiresTheOwnerWithAGuardedExistingTarget(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SCHEMA regional']);

        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A named schema owner requires a declared role.');

        $semantics->analyze('CREATE SCHEMA IF NOT EXISTS regional AUTHORIZATION unknown_role', $context);
    }
}
