<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Handlers;

#[CoversClass(Handlers::class)]
#[Medium]
final class HandlersTest extends TestCase
{
    public function testWriteSpellsTheAliasAfterOpen(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('HANDLER t OPEN h');
        self::assertInstanceOf(OpenHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` OPEN AS `h`', Handlers::write($statement)->toString());
    }
}
