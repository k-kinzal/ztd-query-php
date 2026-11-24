<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumnLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(AddColumnLowering::class)]
#[Small]
final class AddColumnLoweringTest extends TestCase
{
    public function testLowerPublishesTheOrderedHeadingAndStrictModeOfTheResolvedTemporaryTable(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE tasks (id INTEGER)', 'CREATE TEMP TABLE tasks (ID INT, label TEXT) STRICT']);
        $value = $semantics->analyze('ALTER TABLE TASKS ADD extra INT NOT NULL ON CONFLICT FAIL', $declarations);

        self::assertInstanceOf(AddColumn::class, $value);
        self::assertSame('temp', $value->schema->value);
        self::assertSame(['ID', 'label'], array_column($value->priorHeading, 'value'));
        self::assertTrue($value->strict);
        self::assertSame(NotNullConflict::Fail, $value->column->notNull);
        self::assertNull($value->column->default);
    }
}
