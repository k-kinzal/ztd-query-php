<?php

declare(strict_types=1);

namespace BisonParser\Syntax;

use BisonParser\Ast\Declaration\Code;
use BisonParser\Ast\Declaration\CodeProps;
use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Declaration\Define;
use BisonParser\Ast\Declaration\DefineForm;
use BisonParser\Ast\Declaration\Expect;
use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Declaration\InitialAction;
use BisonParser\Ast\Declaration\Option;
use BisonParser\Ast\Declaration\Param;
use BisonParser\Ast\Declaration\ParamKind;
use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Declaration\Symbols\Associativity;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\UnionDeclaration;
use BisonParser\Scanner\Directives;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;

/**
 * Reads one declaration, following the `prologue_declaration` and `grammar_declaration` rules of `parse-gram.y`.
 *
 * @visibility root
 */
final class DeclarationParser
{
    /**
     * @param SymbolListParser $symbols Reads the symbol lists of declarations
     */
    public function __construct(private readonly SymbolListParser $symbols = new SymbolListParser())
    {
    }

    /**
     * Reports whether a token begins a declaration.
     *
     * @param Token $token Token to look at
     *
     * @return bool True for a prologue or a directive that is not a rule modifier
     */
    public function starts(Token $token): bool
    {
        return $token->is(TokenKind::Prologue)
            || ($token->is(TokenKind::Directive) && !in_array($token->text, ['prec', 'dprec', 'merge', 'empty'], true));
    }

    /**
     * Reads the declaration that begins at the current token.
     *
     * @param TokenStream $tokens Tokens positioned on a prologue or directive
     *
     * @return Declaration The declaration
     *
     * @throws SyntaxException When the directive lacks its argument or is not a declaration
     */
    public function parse(TokenStream $tokens): Declaration
    {
        $token = $tokens->next();
        if ($token->is(TokenKind::Prologue)) {
            return new Prologue($token->text, $token->location);
        }
        if (!$token->is(TokenKind::Directive)) {
            throw SyntaxException::unexpected('a declaration', $token->describe(), $token->location);
        }

        return $this->directive($token, $tokens);
    }

    /**
     * Reads the arguments of a directive whose name token was just consumed.
     *
     * @param Token $token The directive token
     * @param TokenStream $tokens Tokens positioned after the directive
     *
     * @return Declaration The declaration
     *
     * @throws SyntaxException When the directive lacks its argument or is not a declaration
     */
    public function directive(Token $token, TokenStream $tokens): Declaration
    {
        $name = $token->text;
        $at = $token->location;
        if (in_array($name, Directives::FLAGS, true)) {
            return new Flag($name, $token->raw, $at);
        }
        if (isset(Directives::OPTIONS[$name])) {
            $string = Directives::OPTIONS[$name] ? $tokens->accept(TokenKind::String) : $tokens->expect(TokenKind::String, "a string after %{$name}");

            return new Option($name, $token->raw, $string?->text, $at);
        }

        return match ($name) {
            'define' => $this->define($tokens, $at),
            'expect', 'expect-rr' => new Expect((int) $tokens->expect(TokenKind::Integer, "a number after %{$name}")->text, $name === 'expect-rr', $at),
            'initial-action' => new InitialAction($tokens->expect(TokenKind::Code, 'braced code after %initial-action')->text, $at),
            'param', 'lex-param', 'parse-param' => new Param(ParamKind::from($name), $this->codes($tokens, $name), $at),
            'union' => new UnionDeclaration($tokens->accept(TokenKind::Identifier)?->text, $tokens->expect(TokenKind::Code, 'braced code after %union')->text, $at),
            'start' => new Start($this->symbols->symbols($tokens), $at),
            'destructor', 'printer' => new CodeProps($name === 'printer', $tokens->expect(TokenKind::Code, "braced code after %{$name}")->text, $this->symbols->targets($tokens), $at),
            'code' => new Code($tokens->accept(TokenKind::Identifier)?->text, $tokens->expect(TokenKind::Code, 'braced code after %code')->text, $at),
            'token', 'nterm' => new SymbolDeclaration(SymbolClass::from($name), $this->symbols->tokenDeclarations($tokens), $at),
            'type' => new SymbolDeclaration(SymbolClass::Type, $this->symbols->typeDeclarations($tokens), $at),
            'left', 'right', 'nonassoc', 'precedence' => new PrecedenceDeclaration(Associativity::from($name), $this->symbols->precedenceDeclarations($tokens), $at),
            default => throw SyntaxException::unexpected('a declaration', "'%{$name}'", $at),
        };
    }

    /**
     * Reads the variable and optional value of a `%define`.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     * @param \BisonParser\Ast\Location $at Where the directive is written
     *
     * @return Define The declaration
     *
     * @throws SyntaxException When no variable follows
     */
    public function define(TokenStream $tokens, \BisonParser\Ast\Location $at): Define
    {
        $variable = $tokens->expect(TokenKind::Identifier, 'a variable after %define')->text;
        $tokens->accept(TokenKind::Equal);
        $form = null;
        if ($tokens->is(TokenKind::Identifier)) {
            $form = DefineForm::Keyword;
        } elseif ($tokens->is(TokenKind::String)) {
            $form = DefineForm::String;
        } elseif ($tokens->is(TokenKind::Code)) {
            $form = DefineForm::Code;
        }
        if ($form === null) {
            return new Define($variable, null, null, $at);
        }

        return new Define($variable, $tokens->next()->text, $form, $at);
    }

    /**
     * Reads the one or more braced arguments of a `%param` directive.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     * @param string $name Directive name, for the error
     *
     * @return list<string> Each argument's code without its braces
     *
     * @throws SyntaxException When no braced argument follows
     */
    public function codes(TokenStream $tokens, string $name): array
    {
        $codes = [$tokens->expect(TokenKind::Code, "braced code after %{$name}")->text];
        while (($code = $tokens->accept(TokenKind::Code)) !== null) {
            $codes[] = $code->text;
        }

        return $codes;
    }
}
