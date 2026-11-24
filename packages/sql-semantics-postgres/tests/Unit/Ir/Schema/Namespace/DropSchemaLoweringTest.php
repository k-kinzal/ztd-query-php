<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DropSchemaLowering::class)]
final class DropSchemaLoweringTest extends TestCase
{
    public function testLowerDistinguishesDeclaredAndKnownAbsentNamespaces(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SCHEMA archive']);
        $value = $semantics->analyze('DROP SCHEMA IF EXISTS "Archive", archive', $context);

        self::assertInstanceOf(DropSchema::class, $value);
        self::assertFalse($value->targets[0]->present);
        self::assertTrue($value->targets[1]->present);
        self::assertSame('DROP SCHEMA IF EXISTS "Archive", "archive" RESTRICT', $value->toString());
    }

    public function testRequiresABoundNamespaceInAnOpenContext(): void
    {
        $semantics = new Semantics();

        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('requires a complete namespace declaration context');

        $semantics->analyze('DROP SCHEMA IF EXISTS archive');
    }
}
