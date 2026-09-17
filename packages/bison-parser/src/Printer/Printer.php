<?php

declare(strict_types=1);

namespace BisonParser\Printer;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Line;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\Rule;

/**
 * Writes a syntax tree back out as a Bison grammar file.
 *
 * The output is not the original text: whitespace and comments are gone,
 * and every declaration and rule is laid out one way. It is a grammar
 * Bison reads to the same tree, which is what lets a tree be checked by
 * printing it and reading it again.
 *
 * @visibility public
 *
 * @example Printing a grammar back
 *     $file = (new \BisonParser\Parser())->parse("%token NUM   // numbers\n%%\nexpr : NUM | expr '+' NUM { \$\$ = \$1 + \$3; } ;");
 *     (new \BisonParser\Printer\Printer())->print($file) // => "%token NUM\n%%\nexpr:\n  NUM\n| expr '+' NUM { \$\$ = \$1 + \$3; }\n;\n"
 */
final class Printer
{
    /**
     * @param DeclarationPrinter $declarations Writes declarations
     * @param RhsPrinter $rhs Writes right-hand sides
     */
    public function __construct(
        private readonly DeclarationPrinter $declarations = new DeclarationPrinter(),
        private readonly RhsPrinter $rhs = new RhsPrinter(),
    ) {
    }

    /**
     * Writes a whole file.
     *
     * @param GrammarFile $file The tree
     *
     * @return string The grammar file text
     */
    public function print(GrammarFile $file): string
    {
        $lines = [];
        foreach ($file->declarations as $declaration) {
            $lines[] = $this->declarations->print($declaration);
        }
        $lines[] = '%%';
        foreach ($file->grammar as $item) {
            $lines[] = $item instanceof Rule ? $this->rule($item) : $this->declarations->print($item) . ($item instanceof Line ? '' : ';');
        }
        $text = implode("\n", $lines) . "\n";
        if ($file->epilogue !== null) {
            $text .= '%%' . $file->epilogue->code;
        }

        return $text;
    }

    /**
     * Writes one rule, one alternative per line.
     *
     * @param Rule $rule The rule
     *
     * @return string The rule text
     */
    public function rule(Rule $rule): string
    {
        $head = $rule->name->value . ($rule->namedReference === null ? '' : "[{$rule->namedReference}]") . ':';
        $lines = [$head];
        foreach ($rule->alternatives as $index => $alternative) {
            $lines[] = ($index === 0 ? '  ' : '| ') . $this->alternative($alternative);
        }
        $lines[] = ';';

        return implode("\n", $lines);
    }

    /**
     * Writes the items of one alternative separated by spaces.
     *
     * @param Alternative $alternative The alternative
     *
     * @return string The items, or `%empty` for an alternative with none
     */
    public function alternative(Alternative $alternative): string
    {
        if ($alternative->items === []) {
            return '%empty';
        }
        $parts = [];
        foreach ($alternative->items as $item) {
            $parts[] = $this->rhs->print($item);
        }

        return implode(' ', $parts);
    }
}
