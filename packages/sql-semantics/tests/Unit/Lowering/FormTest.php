<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Form;

#[CoversClass(Form::class)]
#[Small]
final class FormTest extends TestCase
{
    public function testNodeAnswersTheNonterminalChildAtAPosition(): void
    {
        $child = new Node('expr', 0, []);
        $form = new Form(new Node('where_opt', 1, [new Token(1, 'WHERE', 'WHERE', 0), $child]), 'where_opt: WHERE expr');

        self::assertSame($child, $form->node(1));
        self::assertSame('where_opt: WHERE expr', $form->signature);
    }

    public function testNodeRefusesATerminalPosition(): void
    {
        $form = new Form(new Node('where_opt', 1, [new Token(1, 'WHERE', 'WHERE', 0), new Node('expr', 0, [])]), 'where_opt: WHERE expr');

        $this->expectExceptionMessage('The production has no nonterminal at position 0: where_opt: WHERE expr');

        $form->node(0);
    }

    public function testTokenAnswersTheTerminalChildAtAPosition(): void
    {
        $token = new Token(1, 'WHERE', 'where', 0);
        $form = new Form(new Node('where_opt', 1, [$token, new Node('expr', 0, [])]), 'where_opt: WHERE expr');

        self::assertSame($token, $form->token(0));
        self::assertSame('where', $form->token(0)->text);
    }

    public function testTokenRefusesAMissingPosition(): void
    {
        $form = new Form(new Node('where_opt', 0, []), 'where_opt:');

        $this->expectExceptionMessage('The production has no terminal at position 0: where_opt:');

        $form->token(0);
    }
}
