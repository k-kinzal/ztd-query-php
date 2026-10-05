<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ErrorCode::class)]
#[Medium]
final class ErrorCodeTest extends TestCase
{
    public function testRenderWritesTheCode(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ErrorCode(new Numeral('1051')))->render($out);

        self::assertSame('1051', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheCodesOfADeclarationAndAHandler(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR c, 1052 BEGIN END; END');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $declaration = $body->declarations[0];
        self::assertInstanceOf(ConditionDeclaration::class, $declaration);
        self::assertInstanceOf(ErrorCode::class, $declaration->value);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        self::assertInstanceOf(ConditionName::class, $handler->conditions[0]);
        self::assertInstanceOf(ErrorCode::class, $handler->conditions[1]);

        self::assertSame('1051', $declaration->value->code->text);
        self::assertSame('1052', $handler->conditions[1]->code->text);
        self::assertSame([], $create->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR c, 1052 BEGIN END; END', $create->toString());
    }
}
