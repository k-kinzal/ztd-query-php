<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionImplementation::class)]
#[Small]
final class FunctionImplementationTest extends TestCase
{
    public function testRenderWritesTheMatchingLanguageAndExternalBody(): void
    {
        $body = new ExternalBody(new Text('return 1'));
        $implementation = new FunctionImplementation($body, [new RoutineLanguage(), new RoutineLanguage(new Name('javascript'))]);
        $out = new Output(new Codec(GrammarRelease::MySql910));
        $implementation->render($out);

        self::assertSame($body, $implementation->body);
        self::assertSame("LANGUAGE SQL LANGUAGE javascript AS 'return 1'", (new Lexical())->join($out->pieces()));
    }

    public function testRenderKeepsASqlReturnWithoutADeclarationName(): void
    {
        $implementation = new FunctionImplementation(new ReturnStatement(new NumberLiteral('1')));
        $out = new Output(new Codec(GrammarRelease::MySql847));
        $implementation->render($out);

        self::assertSame('RETURN 1', (new Lexical())->join($out->pieces()));
    }

}
