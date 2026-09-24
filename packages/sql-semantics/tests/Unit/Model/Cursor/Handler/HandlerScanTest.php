<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\HandlerScan;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(HandlerScan::class)]
#[Medium]
final class HandlerScanTest extends TestCase
{
    public function testBindsTheScanStep(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ NEXT');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        self::assertSame(HandlerScan::Next, $statement->scan);
    }
}
