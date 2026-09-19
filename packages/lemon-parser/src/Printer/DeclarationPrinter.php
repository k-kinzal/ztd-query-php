<?php

declare(strict_types=1);

namespace LemonParser\Printer;

use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Declaration;
use LemonParser\Ast\Declaration\Destructor;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\TypeDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\Symbol;
use LogicException;

/**
 * Writes declarations back as Lemon reads them.
 *
 * @visibility root
 */
final class DeclarationPrinter
{
    /**
     * Writes one declaration on one line, without the line break.
     *
     * @param Declaration $declaration The declaration
     *
     * @return string The text
     *
     * @throws LogicException When the declaration is of a class the printer does not know
     */
    public function print(Declaration $declaration): string
    {
        return match ($declaration::class) {
            Directive::class => '%' . $declaration->keyword->value . ' ' . $this->argument($declaration->value, $declaration->form),
            Destructor::class => '%destructor ' . $declaration->symbol->name . ' ' . $this->argument($declaration->value, $declaration->form),
            TypeDeclaration::class => '%type ' . $declaration->symbol->name . ' ' . $this->argument($declaration->value, $declaration->form),
            PrecedenceDeclaration::class => '%' . $declaration->associativity->value . $this->list($declaration->symbols),
            Fallback::class => '%fallback' . $this->list($declaration->symbols),
            TokenDeclaration::class => '%token' . $this->list($declaration->symbols),
            Wildcard::class => '%wildcard' . $this->list($declaration->symbol === null ? [] : [$declaration->symbol]),
            TokenClass::class => '%token_class ' . $declaration->name->name . ' ' . implode('|', array_map(static fn (Symbol $symbol): string => $symbol->name, $declaration->tokens)) . '.',
            default => throw new LogicException('Unknown declaration ' . $declaration::class),
        };
    }

    /**
     * Writes an argument in the form it was read.
     *
     * @param string $value The argument without delimiters
     * @param ArgumentForm $form How it was written
     *
     * @return string The argument with its delimiters
     */
    public function argument(string $value, ArgumentForm $form): string
    {
        return match ($form) {
            ArgumentForm::Code => '{' . $value . '}',
            ArgumentForm::String => '"' . $value . '"',
            ArgumentForm::Word => $value,
        };
    }

    /**
     * Writes a list of symbols with the period that ends it.
     *
     * @param list<Symbol> $symbols The symbols
     *
     * @return string A space before each symbol, then the period
     */
    public function list(array $symbols): string
    {
        $text = '';
        foreach ($symbols as $symbol) {
            $text .= ' ' . $symbol->name;
        }

        return $text . '.';
    }
}
