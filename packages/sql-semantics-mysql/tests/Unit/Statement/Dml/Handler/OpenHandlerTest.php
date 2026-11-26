<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OpenHandler::class)]
#[Medium]
final class OpenHandlerTest extends TestCase
{
    public function testDeriveRelationIsOpen(): void
    {
        $operation = (new Inspection())->inspect('HANDLER h READ FIRST WHERE a');
        self::assertInstanceOf(HandlerScan::class, $operation->statement);

        self::assertFalse($operation->facts->relation($operation->statement->handler)->shape->complete());
        self::assertInstanceOf(ConditionalColumn::class, $operation->facts->scalar($operation->statement->where ?? new ColumnUse(new Name('a')))->resolution);
    }

    public function testRenderWritesTheName(): void
    {
        $out = new Output(new Codec((new Inspection())->profile()->grammar));
        (new OpenHandler(new Name('h')))->render($out);

        self::assertSame('h', (new Lexical())->join($out->pieces()));
    }
}
