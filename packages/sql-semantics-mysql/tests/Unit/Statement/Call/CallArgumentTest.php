<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(CallArgument::class)]
#[Small]
final class CallArgumentTest extends TestCase
{
    public function testRenderWritesTheAlias(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new CallArgument(new ColumnUse(new Name('a')), new Name('total')))->render($out);

        self::assertSame('a AS total', (new Lexical())->join($out->pieces()));
    }

    public function testRenderPreservesAnUnaliasedArgumentsObservableSpelling(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));
        $argument = new CallArgument($expression, layout: new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2')]), spellingProfile: $profile);
        $argument->render($out);

        self::assertSame('1+2', (new Lexical())->join($out->pieces()));
        self::assertSame($expression, $argument->expression);
        self::assertSame($profile, $argument->spellingProfile);
    }

    public function testAnArgumentLayoutCannotReplaceItsOperand(): void
    {
        $this->expectExceptionMessage('A select-item layout must preserve the expression under its spelling profile.');

        new CallArgument(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '3')]));
    }

    public function testAnAliasedArgumentHasNoIndependentLayout(): void
    {
        $this->expectExceptionMessage('An aliased select item is named by its alias and keeps no layout.');

        new CallArgument(new NumberLiteral('1'), new Name('value'), layout: new Layout([new Spelled('', '1')]));
    }

    public function testAnArgumentWithoutLayoutHasNoSpellingProfile(): void
    {
        $this->expectExceptionMessage('A select item without a layout keeps no spelling profile.');

        new CallArgument(new NumberLiteral('1'), spellingProfile: (new Platform())->profile('mysql-8.4.7', null, ParameterStyle::Native));
    }
}
