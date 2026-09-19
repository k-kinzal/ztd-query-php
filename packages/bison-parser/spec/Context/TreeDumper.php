<?php

declare(strict_types=1);

namespace Spec\Context;

use function array_map;

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
use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Declaration\UnionDeclaration;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Line;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\RhsItem;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;

use function implode;

use LogicException;

use function ord;
use function sprintf;
use function str_repeat;
use function str_split;

/**
 * Writes a syntax tree as one line per node, so that a scenario can state the
 * whole result of a parse in a docstring.
 *
 * Each line names the node class and its fields; children are indented by two
 * spaces.  The two grammar-file sections are separated by a `%%` line, and the
 * epilogue follows a second one.  Literal symbols and aliases are written in
 * their canonical spelling followed by `spelled <source>` when the source
 * spelled them differently.  Positions are not shown.
 */
final class TreeDumper
{
    /**
     * Renders a whole tree.
     *
     * @param GrammarFile $file The tree
     *
     * @return string One line per node, without a final newline
     */
    public function dump(GrammarFile $file): string
    {
        $lines = [];
        foreach ($file->declarations as $declaration) {
            $this->declaration($declaration, $lines);
        }
        $lines[] = '%%';
        foreach ($file->grammar as $item) {
            if ($item instanceof Rule) {
                $this->rule($item, $lines);
            } else {
                $this->declaration($item, $lines);
            }
        }
        if ($file->epilogue !== null) {
            $lines[] = '%%';
            $lines[] = 'Epilogue ' . $this->code($file->epilogue->code);
        }

        return implode("\n", $lines);
    }

    /**
     * Appends the lines of a declaration and of its symbol entries.
     *
     * @param Declaration $declaration The declaration
     * @param list<string> $lines The lines written so far
     */
    public function declaration(Declaration $declaration, array &$lines): void
    {
        $lines[] = $this->declarationLine($declaration);
        if ($declaration instanceof SymbolDeclaration || $declaration instanceof PrecedenceDeclaration) {
            foreach ($declaration->entries as $entry) {
                $lines[] = $this->indent(1) . $this->entry($entry);
            }
        }
    }

    /**
     * The line of a declaration: its class and its fields.
     *
     * @param Declaration $declaration The declaration
     *
     * @return string The line
     *
     * @throws LogicException When the declaration is of a class this dumper does not know
     */
    public function declarationLine(Declaration $declaration): string
    {
        if ($declaration instanceof Prologue) {
            return 'Prologue ' . $this->code($declaration->code);
        }
        if ($declaration instanceof Code) {
            return 'Code ' . ($declaration->qualifier === null ? '' : $declaration->qualifier . ' ') . $this->code($declaration->code);
        }
        if ($declaration instanceof Define) {
            return $this->define($declaration);
        }
        if ($declaration instanceof Flag) {
            return 'Flag ' . $declaration->name . $this->spelled('%' . $declaration->name, $declaration->raw);
        }
        if ($declaration instanceof Option) {
            return 'Option ' . $declaration->name
                . ($declaration->value === null ? '' : ' ' . $this->string($declaration->value))
                . $this->spelled('%' . $declaration->name, $declaration->raw);
        }
        if ($declaration instanceof Expect) {
            return 'Expect ' . ($declaration->reduceReduce ? 'reduce/reduce ' : '') . $declaration->count;
        }
        if ($declaration instanceof InitialAction) {
            return 'InitialAction ' . $this->code($declaration->code);
        }
        if ($declaration instanceof Param) {
            return 'Param ' . $declaration->kind->value . ' ' . implode(' ', array_map($this->code(...), $declaration->codes));
        }
        if ($declaration instanceof UnionDeclaration) {
            return 'UnionDeclaration ' . ($declaration->name === null ? '' : $declaration->name . ' ') . $this->code($declaration->code);
        }
        if ($declaration instanceof Start) {
            return 'Start ' . implode(' ', array_map($this->symbol(...), $declaration->symbols));
        }
        if ($declaration instanceof CodeProps) {
            return 'CodeProps ' . ($declaration->printer ? 'printer' : 'destructor') . ' ' . $this->code($declaration->code)
                . ' ' . implode(' ', array_map($this->target(...), $declaration->targets));
        }
        if ($declaration instanceof SymbolDeclaration) {
            return 'SymbolDeclaration ' . $declaration->class->value;
        }
        if ($declaration instanceof PrecedenceDeclaration) {
            return 'PrecedenceDeclaration ' . $declaration->associativity->value;
        }
        if ($declaration instanceof Line) {
            return $this->line($declaration);
        }

        throw new LogicException('No rendering for ' . $declaration::class);
    }

    /**
     * The line of a `%define`, quoting the value the way its form does.
     *
     * @param Define $define The declaration
     *
     * @return string The line
     */
    public function define(Define $define): string
    {
        if ($define->form === null) {
            return 'Define ' . $define->variable;
        }
        $value = (string) $define->value;

        return 'Define ' . $define->variable . ' = ' . match ($define->form) {
            DefineForm::Keyword => $value,
            DefineForm::String => $this->string($value),
            DefineForm::Code => $this->code($value),
        };
    }

    /**
     * The line of a symbol entry: tag, symbol, number and alias.
     *
     * @param SymbolEntry $entry The entry
     *
     * @return string The line
     */
    public function entry(SymbolEntry $entry): string
    {
        $text = 'SymbolEntry';
        if ($entry->tag !== null) {
            $text .= ' <' . $entry->tag . '>';
        }
        $text .= ' ' . $this->symbol($entry->symbol);
        if ($entry->number !== null) {
            $text .= ' ' . $entry->number;
        }
        if ($entry->alias !== null) {
            $text .= ' ' . $this->alias($entry->alias);
        }

        return $text;
    }

    /**
     * A string alias, translatable or not, with its spelling when that differs.
     *
     * @param Alias $alias The alias
     *
     * @return string The rendering
     */
    public function alias(Alias $alias): string
    {
        $canonical = $alias->translatable ? '_(' . $this->string($alias->text) . ')' : $this->string($alias->text);

        return $canonical . $this->spelled($canonical, $alias->spelling ?? $canonical);
    }

    /**
     * Appends the lines of a rule, its alternatives and their items.
     *
     * @param Rule $rule The rule
     * @param list<string> $lines The lines written so far
     */
    public function rule(Rule $rule, array &$lines): void
    {
        $lines[] = 'Rule ' . $this->symbol($rule->name) . $this->reference($rule->namedReference);
        foreach ($rule->alternatives as $alternative) {
            $lines[] = $this->indent(1) . 'Alternative';
            foreach ($alternative->items as $item) {
                $lines[] = $this->indent(2) . $this->item($item);
            }
        }
    }

    /**
     * The line of a right-hand side item.
     *
     * @param RhsItem $item The item
     *
     * @return string The line
     *
     * @throws LogicException When the item is of a class this dumper does not know
     */
    public function item(RhsItem $item): string
    {
        if ($item instanceof SymbolItem) {
            return 'SymbolItem ' . $this->symbol($item->symbol) . $this->reference($item->namedReference);
        }
        if ($item instanceof Action) {
            return 'Action ' . ($item->tag === null ? '' : '<' . $item->tag . '> ') . $this->code($item->code) . $this->reference($item->namedReference);
        }
        if ($item instanceof Predicate) {
            return 'Predicate ' . $this->code($item->code);
        }
        if ($item instanceof EmptyItem) {
            return 'EmptyItem';
        }
        if ($item instanceof PrecItem) {
            return 'PrecItem ' . $this->symbol($item->symbol);
        }
        if ($item instanceof DprecItem) {
            return 'DprecItem ' . $item->value;
        }
        if ($item instanceof MergeItem) {
            return 'MergeItem <' . $item->tag . '>';
        }
        if ($item instanceof ExpectItem) {
            return 'ExpectItem ' . ($item->reduceReduce ? 'reduce/reduce ' : '') . $item->count;
        }
        if ($item instanceof Line) {
            return $this->line($item);
        }

        throw new LogicException('No rendering for ' . $item::class);
    }

    /**
     * The line of a `#line` directive.
     *
     * @param Line $line The directive
     *
     * @return string The line
     */
    public function line(Line $line): string
    {
        return 'Line ' . $line->line . ($line->file === null ? '' : ' ' . $this->string($line->file));
    }

    /**
     * A symbol in its canonical spelling, with the source spelling when that differs.
     *
     * @param Symbol $symbol The symbol
     *
     * @return string The rendering
     */
    public function symbol(Symbol $symbol): string
    {
        $canonical = match ($symbol->kind) {
            SymbolKind::Identifier => $symbol->value,
            SymbolKind::CharLiteral => "'" . $this->escape($symbol->value, "'") . "'",
            SymbolKind::String => $this->string($symbol->value),
        };

        return $canonical . $this->spelled($canonical, $symbol->spelling ?? $canonical);
    }

    /**
     * A target of `%destructor` or `%printer`: a symbol or a tag.
     *
     * @param Symbol|Tag $target The target
     *
     * @return string The rendering
     */
    public function target(Symbol|Tag $target): string
    {
        return $target instanceof Tag ? '<' . $target->name . '>' : $this->symbol($target);
    }

    /**
     * A bracketed named reference, or nothing.
     *
     * @param string|null $name The name, if any
     *
     * @return string The rendering, starting with a space when present
     */
    public function reference(?string $name): string
    {
        return $name === null ? '' : ' [' . $name . ']';
    }

    /**
     * The `spelled` suffix, when the source spelling differs from the canonical one.
     *
     * @param string $canonical The canonical spelling
     * @param string $spelling The spelling in the source
     *
     * @return string The suffix, or nothing
     */
    public function spelled(string $canonical, string $spelling): string
    {
        return $spelling === $canonical ? '' : ' spelled ' . $spelling;
    }

    /**
     * A string in double quotes, escaped.
     *
     * @param string $text The bytes
     *
     * @return string The rendering
     */
    public function string(string $text): string
    {
        return '"' . $this->escape($text, '"') . '"';
    }

    /**
     * Code in braces, escaped so that it stays on one line.
     *
     * @param string $code The code
     *
     * @return string The rendering
     */
    public function code(string $code): string
    {
        return '{' . $this->escape($code, '') . '}';
    }

    /**
     * Escapes backslashes, the quote, and control characters.
     *
     * @param string $bytes The bytes
     * @param string $quote The quote to escape, or an empty string for none
     *
     * @return string The escaped bytes
     */
    public function escape(string $bytes, string $quote): string
    {
        if ($bytes === '') {
            return '';
        }
        $escaped = '';
        foreach (str_split($bytes) as $byte) {
            $escaped .= match (true) {
                $byte === '\\' => '\\\\',
                $byte === "\n" => '\\n',
                $byte === "\r" => '\\r',
                $byte === "\t" => '\\t',
                $byte === $quote => '\\' . $quote,
                ord($byte) < 0x20 || ord($byte) === 0x7F => sprintf('\\x%02x', ord($byte)),
                default => $byte,
            };
        }

        return $escaped;
    }

    /**
     * The indentation of a depth.
     *
     * @param int $depth Two spaces per level
     *
     * @return string The spaces
     */
    public function indent(int $depth): string
    {
        return str_repeat('  ', $depth);
    }
}
