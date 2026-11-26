<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaTargetLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaTargetLowering::class)]
final class SchemaTargetLoweringTest extends TestCase
{
    public function testLowerResolvesTheSchemaFromAnAbsoluteTableDeclaration(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE "Sales".accounts (id integer)']);
        $value = $semantics->analyze('ALTER SCHEMA "Sales" RENAME TO archive', $context);

        self::assertInstanceOf(RenameSchema::class, $value);
        self::assertSame('Sales', $value->schema->value);
    }

    public function testLowerRequiresAResolvedSource(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('ALTER SCHEMA requires a declared source namespace.');

        (new Semantics())->analyze('ALTER SCHEMA unknown_scope RENAME TO archive');
    }
}
