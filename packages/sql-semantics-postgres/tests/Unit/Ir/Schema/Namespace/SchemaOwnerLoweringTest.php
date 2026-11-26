<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaOwnerLowering::class)]
final class SchemaOwnerLoweringTest extends TestCase
{
    public function testLowerResolvesCurrentRoleAsTheEffectiveAuthorization(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('ALTER SCHEMA public OWNER TO CURRENT_ROLE');

        self::assertInstanceOf(ChangeSchemaOwner::class, $value);
        self::assertSame(SchemaSessionRole::Effective, $value->owner);
    }
}
