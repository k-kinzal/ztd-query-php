<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Rule;

use SqlFaker\Grammar\Source\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolNode;

/**
 * The alternative currently being read.
 *
 * An alternative is finished by whatever comes after it — a pipe, a semicolon,
 * or the start of the next rule — rather than by a token of its own, so its
 * parts accumulate until one of those appears. Holding them here means the
 * reader flushes and starts over in one call instead of resetting five
 * variables at each of the three places an alternative can end, which is where
 * a forgotten reset would leave one alternative's action attached to the next.
 */
final class BisonAlternativeDraft
{
    /**
     * @var list<BisonSymbolNode>
     */
    private array $symbols = [];

    private ?string $action = null;

    private ?string $precedenceSymbol = null;

    private ?int $dynamicPrecedence = null;

    private ?string $mergeFunction = null;

    /**
     * Appends a symbol to the right-hand side being read.
     *
     * @param BisonSymbolNode $symbol Symbol to append
     */
    public function addSymbol(BisonSymbolNode $symbol): void
    {
        $this->symbols[] = $symbol;
    }

    /**
     * Attaches the semantic action that runs when this alternative reduces.
     *
     * @param string $action Host-language code, without its braces
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * Overrides the alternative's precedence with that of a named terminal.
     *
     * @param string|null $symbol Terminal to take the precedence of, or null when `%prec` named none
     */
    public function setPrecedenceSymbol(?string $symbol): void
    {
        $this->precedenceSymbol = $symbol;
    }

    /**
     * Sets the dynamic precedence used to resolve an ambiguity at parse time.
     *
     * @param int|null $precedence The declared rank, or null when `%dprec` named none
     */
    public function setDynamicPrecedence(?int $precedence): void
    {
        $this->dynamicPrecedence = $precedence;
    }

    /**
     * Sets the function that merges two ambiguous parses of this alternative.
     *
     * @param string|null $function Merge function name, or null when `%merge` named none
     */
    public function setMergeFunction(?string $function): void
    {
        $this->mergeFunction = $function;
    }

    /**
     * Takes the alternative that has been read and starts a new one.
     *
     * @return BisonAlternativeNode The completed alternative
     */
    public function complete(): BisonAlternativeNode
    {
        $completed = new BisonAlternativeNode(
            $this->symbols,
            $this->action,
            $this->precedenceSymbol,
            $this->dynamicPrecedence,
            $this->mergeFunction,
        );

        $this->symbols = [];
        $this->action = null;
        $this->precedenceSymbol = null;
        $this->dynamicPrecedence = null;
        $this->mergeFunction = null;

        return $completed;
    }
}
