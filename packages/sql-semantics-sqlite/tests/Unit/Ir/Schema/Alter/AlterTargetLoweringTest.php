<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AlterTargetLowering;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(AlterTargetLowering::class)]
#[Small]
final class AlterTargetLoweringTest extends TestCase
{
    public function testResolveUsesAnAbsoluteNameWithAnOpenCatalog(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE t (id INT)'], false);
        $value = $semantics->analyze('ALTER TABLE MAIN.T ADD extra TEXT', $declarations);

        self::assertInstanceOf(AddColumn::class, $value);
        self::assertSame('main', $value->schema->value);
        self::assertSame('t', $value->table->value);
    }
}
