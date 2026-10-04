<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Into;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(IntoDumpfile::class)]
#[Medium]
final class IntoDumpfileTest extends TestCase
{
    public function testRenderWritesTheFile(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("select a from t into dumpfile 'a.bin'");

        self::assertSame("SELECT a FROM t INTO DUMPFILE 'a.bin'", $operation->toString());
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(IntoDumpfile::class, $operation->statement->into);
        self::assertSame('a.bin', $operation->statement->into->file->value);
    }
}
