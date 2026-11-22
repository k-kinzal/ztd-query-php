<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;

/**
 * One projected expression of a select list with its optional alias.
 *
 * MySQL names an item without alias after the text of its expression as the
 * statement writes it (MYSQL-SELECT-ITEM-NAME-001), so such an item keeps the
 * layout of that text (CORE-SPELLING-001): the expression is rendered in the
 * spelling and with the trivia it was written with, and the output name
 * follows from it. An item built without a layout is written, and named, in
 * the canonical rendering of its expression. An aliased item is named by its
 * alias and keeps no layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility SqlSemantics
 */
final class SelectExpression implements SelectItem
{
    use Snapshot;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name
     * @param Layout|null $layout How the expression of an unaliased item is written; null for the canonical rendering
     * @param AliasMark $mark Whether the alias is written after AS; `=` is not a select alias
     * @throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction When an aliased item is given a layout, or the mark does not fit the alias
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null, public readonly ?Layout $layout = null, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias === null || $layout === null, 'An aliased select item is named by its alias and keeps no layout.');
        Check::input($layout === null || $layout->trail === '', 'MySQL names a select item without the trivia after its expression, so its layout has no trailing trivia.');
        Check::input($mark !== AliasMark::Equals && ($alias !== null || $mark === AliasMark::As), 'A select alias is written with or without AS, and an item without alias has no alias mark.');
    }

    /**
     * Writes the expression, in its layout when it has one, and the alias.
     */
    public function render(Output $out): void
    {
        $out->layout($this->layout, $this->expression);
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
