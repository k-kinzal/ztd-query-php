<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Rendering\Canonical;

/**
 * One projected expression of a selection or of a RETURNING clause with its optional alias.
 *
 * SQLite names a result column without an alias after the text of its
 * expression from its first token to the start of the next one
 * (SQLITE-RESULT-NAME-001), so such a column keeps that text as a layout
 * (CORE-SPELLING-001): one spelling per token the expression renders, with
 * the comments and whitespace written between them, and the trivia after the
 * expression when it holds a comment. The expression is written in that spelling, so the rendered
 * SQL names the column as the analyzed SQL did. A layout is kept only when it
 * differs from the canonical spelling; a column without one is written in
 * the canonical spelling and is named after it, which is the text the
 * database reads. An aliased column is named by its alias and keeps no layout.
 *
 * @visibility SqlSemantics
 */
final class ResultColumn implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name
     * @param Layout|null $layout The spelling of the expression of a column without an alias when it is not the canonical one
     * @param bool $as Whether the keyword AS introduces the alias; SQLite names a column after the text of an expression, which includes it
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When AS is left out without an alias, an aliased column has a layout, or the layout does not spell one token per rendered token, keeps whitespace alone after the expression, or is the canonical spelling
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null, public readonly ?Layout $layout = null, public readonly bool $as = true)
    {
        Check::input($as || $alias !== null, 'AS is left out only before an alias.');
        Check::input($alias === null || $layout === null, 'An aliased result column is named by its alias and keeps no layout.');
        if ($layout !== null) {
            $canonical = (new Canonical())->layout($expression);
            Check::input(count($canonical->tokens) === count($layout->tokens), 'The layout of a result column spells one token per token its expression renders.');
            Check::input((new Canonical())->trail($layout->trail) === $layout->trail, 'The layout of a result column keeps the trivia after its expression only when it holds a comment.');
            Check::input(!(new Canonical())->same($canonical, $layout), 'A result column written in the canonical spelling has no layout.');
        }
    }

    /**
     * Writes the expression in its spelling and the alias.
     */
    public function render(Output $out): void
    {
        $out->layout($this->layout, $this->expression);
        if ($this->alias !== null) {
            if ($this->as) {
                $out->keyword('AS');
            }
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
