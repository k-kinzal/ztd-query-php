<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;

#[CoversClass(OpenHandler::class)]
#[Medium]
final class OpenHandlerTest extends TestCase
{
    public function testDeriveRelationIsOpen(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ FIRST WHERE a');
        self::assertInstanceOf(HandlerScan::class, $operation->statement);

        self::assertFalse($operation->facts->relation($operation->statement->handler)->shape->complete());
        self::assertInstanceOf(ConditionalColumn::class, $operation->facts->scalar($operation->statement->where ?? new ColumnUse(new Name('a')))->resolution);
    }

    public function testRenderWritesTheName(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new OpenHandler(new Name('h')))->render($out);

        self::assertSame('h', (new Lexical())->join($out->pieces()));
    }
}
