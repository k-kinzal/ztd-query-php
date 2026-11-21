<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TypeDescriptor::class)]
#[Medium]
final class TypeDescriptorTest extends TestCase
{
    public function testNameIsTheNameTheDatabaseReports(): void
    {
        $table = (new Semantics())->analyze('CREATE TABLE t (a INTEGER, b VARCHAR(10), c)')->declarations()[0];

        self::assertSame('INTEGER', $table->columns[0]->type->name());
        self::assertSame('VARCHAR(10)', $table->columns[1]->type->name());
        self::assertSame('', $table->columns[2]->type->name());
    }
}
