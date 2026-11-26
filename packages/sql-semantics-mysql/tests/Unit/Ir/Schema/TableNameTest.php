<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(TableName::class)]
#[Small]
final class TableNameTest extends TestCase
{
    public function testQuotedUsesTheAbsoluteName(): void
    {
        $table = new TableName(new Identifier('app'), new Identifier('a'));

        self::assertSame('`app`.`a`', $table->quoted());
    }
}
