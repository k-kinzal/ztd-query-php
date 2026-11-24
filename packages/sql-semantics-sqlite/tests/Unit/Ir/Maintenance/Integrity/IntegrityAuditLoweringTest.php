<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Integrity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAuditLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityLimit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(IntegrityAuditLowering::class)]
#[Small]
final class IntegrityAuditLoweringTest extends TestCase
{
    public function testLowerSeparatesNumericTokensFromNamesAndPreservesImplicitAccess(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main."1"(id INT)', 'CREATE TEMP TABLE "1"(id INT)']);
        $count = $semantics->analyze('PRAGMA quick_check(-1.7)', $context);
        $table = $semantics->analyze("PRAGMA quick_check('1')", $context);

        self::assertInstanceOf(IntegrityAudit::class, $count);
        self::assertInstanceOf(IntegrityLimit::class, $count->target);
        self::assertSame(1, $count->target->value);
        self::assertSame('PRAGMA quick_check(1)', $count->toString());
        self::assertInstanceOf(IntegrityAudit::class, $table);
        self::assertInstanceOf(RelationTarget::class, $table->target);
        self::assertNull($table->schema);
        self::assertSame('temp', $table->target->schema->value);
        self::assertSame('PRAGMA quick_check("1")', $table->toString());
    }
}
