<?php

declare(strict_types=1);

namespace Spec\Context;

use function array_map;
use function implode;

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
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LogicException;

use function ord;
use function sprintf;
use function str_split;

/**
 * Writes a syntax tree as one line per node, so that a scenario can state the
 * whole result of a parse in a docstring.
 *
 * Each line names the node class and its fields, in file order. A rule's
 * positions, precedence mark, code and NEVER-REDUCE flag are indented under
 * it by two spaces. Braced arguments are written in braces, strings in
 * double quotes, and words bare. Positions are not shown.
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
        foreach ($file->items as $item) {
            if ($item instanceof Rule) {
                $this->rule($item, $lines);
            } else {
                $lines[] = $this->declaration($item);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Appends the lines of a rule: its left-hand side, then each position,
     * the precedence mark, the code and the NEVER-REDUCE flag when present.
     *
     * @param Rule $rule The rule
     * @param list<string> $lines The lines written so far
     */
    public function rule(Rule $rule, array &$lines): void
    {
        $lines[] = 'Rule ' . $rule->lhs->name . $this->alias($rule->lhsAlias);
        foreach ($rule->items as $item) {
            $lines[] = '  Item ' . implode('|', $this->names($item->symbols)) . $this->alias($item->alias);
        }
        if ($rule->precedence !== null) {
            $lines[] = '  Precedence ' . $rule->precedence->name;
        }
        if ($rule->code !== null) {
            $lines[] = '  Code ' . $this->code($rule->code->code);
        }
        if ($rule->neverReduce) {
            $lines[] = '  NeverReduce';
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
    public function declaration(Declaration $declaration): string
    {
        if ($declaration instanceof Directive) {
            return 'Directive ' . $declaration->keyword->value . ' ' . $this->argument($declaration->value, $declaration->form);
        }
        if ($declaration instanceof Destructor) {
            return 'Destructor ' . $declaration->symbol->name . ' ' . $this->argument($declaration->value, $declaration->form);
        }
        if ($declaration instanceof TypeDeclaration) {
            return 'TypeDeclaration ' . $declaration->symbol->name . ' ' . $this->argument($declaration->value, $declaration->form);
        }
        if ($declaration instanceof PrecedenceDeclaration) {
            return $this->listed('PrecedenceDeclaration ' . $declaration->associativity->value, $declaration->symbols);
        }
        if ($declaration instanceof Fallback) {
            return $this->listed('Fallback', $declaration->symbols);
        }
        if ($declaration instanceof TokenDeclaration) {
            return $this->listed('TokenDeclaration', $declaration->symbols);
        }
        if ($declaration instanceof Wildcard) {
            return 'Wildcard' . ($declaration->symbol === null ? '' : ' ' . $declaration->symbol->name);
        }
        if ($declaration instanceof TokenClass) {
            return $this->listed('TokenClass ' . $declaration->name->name, $declaration->tokens);
        }

        throw new LogicException('No rendering for ' . $declaration::class);
    }

    /**
     * A declaration line followed by the names of its symbols, if any.
     *
     * @param string $head The class and fixed fields
     * @param list<Symbol> $symbols The symbols
     *
     * @return string The line
     */
    public function listed(string $head, array $symbols): string
    {
        return $symbols === [] ? $head : $head . ' ' . implode(' ', $this->names($symbols));
    }

    /**
     * The names of symbols.
     *
     * @param list<Symbol> $symbols The symbols
     *
     * @return list<string> Their names
     */
    public function names(array $symbols): array
    {
        return array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols);
    }

    /**
     * A declaration argument in the form it was written: braced code,
     * a quoted string, or a bare word.
     *
     * @param string $value The argument
     * @param ArgumentForm $form How it was written
     *
     * @return string The rendering
     */
    public function argument(string $value, ArgumentForm $form): string
    {
        return match ($form) {
            ArgumentForm::Code => $this->code($value),
            ArgumentForm::String => '"' . $this->escape($value) . '"',
            ArgumentForm::Word => $value,
        };
    }

    /**
     * An alias in parentheses, or nothing.
     *
     * @param string|null $alias The alias, if any
     *
     * @return string The rendering
     */
    public function alias(?string $alias): string
    {
        return $alias === null ? '' : '(' . $alias . ')';
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
        return '{' . $this->escape($code) . '}';
    }

    /**
     * Escapes backslashes and control characters.
     *
     * @param string $bytes The bytes
     *
     * @return string The escaped bytes
     */
    public function escape(string $bytes): string
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
                ord($byte) < 0x20 || ord($byte) === 0x7F => sprintf('\\x%02x', ord($byte)),
                default => $byte,
            };
        }

        return $escaped;
    }
}
