<?php

declare(strict_types=1);

namespace BisonParser\Printer;

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
use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Declaration\UnionDeclaration;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\Tag;
use LogicException;

/**
 * Writes declarations the way Bison spells them.
 *
 * @visibility root
 */
final class DeclarationPrinter
{
    /**
     * @param SymbolPrinter $symbols Writes symbols and literals
     */
    public function __construct(private readonly SymbolPrinter $symbols = new SymbolPrinter())
    {
    }

    /**
     * Writes one declaration.
     *
     * @param Declaration $declaration The declaration
     *
     * @return string The declaration text
     *
     * @throws LogicException When the declaration is of a class the printer does not know
     */
    public function print(Declaration $declaration): string
    {
        return match ($declaration::class) {
            Prologue::class => '%{' . $declaration->code . '%}',
            Flag::class => $declaration->raw,
            Option::class => $declaration->raw . ($declaration->value === null ? '' : ' ' . $this->symbols->string($declaration->value)),
            Define::class => $this->define($declaration),
            Expect::class => ($declaration->reduceReduce ? '%expect-rr ' : '%expect ') . $declaration->count,
            InitialAction::class => '%initial-action ' . $this->symbols->code($declaration->code),
            Param::class => '%' . $declaration->kind->value . ' ' . implode(' ', array_map($this->symbols->code(...), $declaration->codes)),
            UnionDeclaration::class => '%union ' . ($declaration->name === null ? '' : $declaration->name . ' ') . $this->symbols->code($declaration->code),
            Start::class => '%start ' . implode(' ', array_map($this->symbols->symbol(...), $declaration->symbols)),
            CodeProps::class => ($declaration->printer ? '%printer ' : '%destructor ') . $this->symbols->code($declaration->code) . ' ' . $this->targets($declaration->targets),
            Code::class => '%code ' . ($declaration->qualifier === null ? '' : $declaration->qualifier . ' ') . $this->symbols->code($declaration->code),
            SymbolDeclaration::class => '%' . $declaration->class->value . $this->entries($declaration->entries),
            PrecedenceDeclaration::class => '%' . $declaration->associativity->value . $this->entries($declaration->entries),
            default => throw new LogicException('Unknown declaration ' . $declaration::class),
        };
    }

    /**
     * Writes a `%define`.
     *
     * @param Define $define The declaration
     *
     * @return string The declaration text
     */
    public function define(Define $define): string
    {
        $text = '%define ' . $define->variable;
        if ($define->form === null || $define->value === null) {
            return $text;
        }

        return $text . ' ' . match ($define->form) {
            DefineForm::Keyword => $define->value,
            DefineForm::String => $this->symbols->string($define->value),
            DefineForm::Code => $this->symbols->code($define->value),
        };
    }

    /**
     * Writes the symbols and tags of a `%destructor` or `%printer`.
     *
     * @param list<Symbol|Tag> $targets The targets
     *
     * @return string The targets separated by spaces
     */
    public function targets(array $targets): string
    {
        $parts = [];
        foreach ($targets as $target) {
            $parts[] = $target instanceof Tag ? $this->symbols->tag($target) : $this->symbols->symbol($target);
        }

        return implode(' ', $parts);
    }

    /**
     * Writes the entries of a symbol or precedence declaration, each tag once where it takes effect.
     *
     * @param list<SymbolEntry> $entries The entries
     *
     * @return string The entries, each preceded by a space
     */
    public function entries(array $entries): string
    {
        $text = '';
        $tag = null;
        foreach ($entries as $entry) {
            if ($entry->tag !== null && $entry->tag !== $tag) {
                $tag = $entry->tag;
                $text .= " <{$tag}>";
            }
            $text .= ' ' . $this->symbols->symbol($entry->symbol);
            if ($entry->number !== null) {
                $text .= ' ' . $entry->number;
            }
            if ($entry->alias !== null) {
                $text .= ' ' . ($entry->alias->translatable ? '_(' . $this->symbols->string($entry->alias->text) . ')' : $this->symbols->string($entry->alias->text));
            }
        }

        return $text;
    }
}
