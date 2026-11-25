<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Rename;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;

#[CoversClass(IndexRename::class)]
#[Medium]
final class IndexRenameTest extends TestCase
{
    public function testToStringQuotesBothIndexOperands(): void
    {
        $move = new IndexRename(new Identifier('old key'), new Identifier('new ` key'));

        self::assertSame('RENAME INDEX `old key` TO `new `` key`', $move->toString());
    }
}
