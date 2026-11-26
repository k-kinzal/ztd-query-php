<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ForeignKeyStringLowering::class)]
#[Medium]
final class ForeignKeyStringLoweringTest extends TestCase
{
    public function testStorageFindsExactDeclaredTextAndByteDomains(): void
    {
        $ddl = 'CREATE TABLE app.types (t CHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, b VARBINARY(9))';
        $table = (new Inspection())->inspect($ddl)->declarations()[0];
        $lowering = new ForeignKeyStringLowering();
        $text = $lowering->storage($table->columns[0]);
        $binary = $lowering->storage($table->columns[1]);

        self::assertSame(ForeignKeyTextCollation::Unicode, $text?->collation);
        self::assertSame(32, $text->keyBytes());
        self::assertSame(ForeignKeyStringKind::Varbinary, $binary?->kind);
        self::assertSame(9, $binary->keyBytes());
    }

    public function testLengthReadsFixedDefaultAndExplicitVariableWidth(): void
    {
        $lowering = new ForeignKeyStringLowering();

        self::assertSame(1, $lowering->length(null, ForeignKeyStringKind::Binary));
        self::assertSame(768, $lowering->length('768', ForeignKeyStringKind::Varbinary));
    }

    public function testCollationReadsTheNativeUtf8mb4Identity(): void
    {
        $ddl = 'CREATE TABLE app.labels (v CHAR(8) COLLATE utf8mb4_general_ci)';
        $column = (new Inspection())->inspect($ddl)->declarations()[0]->columns[0];

        self::assertSame(ForeignKeyTextCollation::General, (new ForeignKeyStringLowering())->collation($column, null));
    }
}
