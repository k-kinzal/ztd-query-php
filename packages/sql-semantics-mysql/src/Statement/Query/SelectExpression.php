<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Spelling\Layout;

/**
 * One projected expression of a select list with its optional alias.
 *
 * MySQL names an item without alias after the text of its expression as the
 * statement writes it (MYSQL-SELECT-ITEM-NAME-001), so such an item keeps the
 * layout of that text (CORE-SPELLING-001): the expression is rendered in the
 * spelling and with the trivia it was written with, and the output name
 * follows from it. An item built without a layout is written, and named, in
 * the canonical rendering of its expression. An aliased item is named by its
 * alias and keeps no layout. A layout also retains the language profile that
 * interprets its token spellings; its equivalence to the expression is checked
 * during construction, without retaining parser nodes or source SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading a projected expression and its alias
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a AS total FROM t');
 *     [$query->statement->query->items[0]->expression->name->value, $query->statement->query->items[0]->alias?->value] // => ['a', 'total']
 * @example Reading the text an unaliased item is written as
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1+1');
 *     [$query->statement->query->items[0]->layout?->text(), $query->field(0)->name?->value, $query->toString()] // => ['1+1', '1+1', 'SELECT 1+1']
 */
final class SelectExpression implements SelectItem
{
    use Snapshot;

    /**
     * The lexical interpretation of the layout; absent when there is no layout.
     */
    public readonly ?LanguageProfile $spellingProfile;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name
     * @param Layout|null $layout How the expression of an unaliased item is written; null for the canonical rendering
     * @param AliasMark $mark Whether the alias is written after AS; `=` is not a select alias
     * @param LanguageProfile|null $spellingProfile The profile interpreting the layout, defaulting to MySQL 8.4; absent without a layout
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an aliased item is given a layout, or the mark does not fit the alias
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null, public readonly ?Layout $layout = null, public readonly AliasMark $mark = AliasMark::As, ?LanguageProfile $spellingProfile = null)
    {
        Check::input($alias === null || $layout === null, 'An aliased select item is named by its alias and keeps no layout.');
        Check::input($layout === null || $layout->trail === '', 'MySQL names a select item without the trivia after its expression, so its layout has no trailing trivia.');
        Check::input($mark !== AliasMark::Equals && ($alias !== null || $mark === AliasMark::As), 'A select alias is written with or without AS, and an item without alias has no alias mark.');
        Check::input($layout !== null || $spellingProfile === null, 'A select item without a layout keeps no spelling profile.');
        $this->spellingProfile = $layout === null ? null : ($spellingProfile ?? new LanguageProfile(GrammarRelease::MySql847));
        if ($layout !== null && $this->spellingProfile !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ItemLayouts())->check($expression, $layout, $this->spellingProfile);
        }
    }

    /**
     * Writes the expression, in its layout when it has one, and the alias.
     */
    public function render(Output $out): void
    {
        $out->layout($this->layout, $this->expression, $this->spellingProfile?->grammar === GrammarRelease::MySql5651);
        if ($this->alias !== null) {
            $this->mark->write($out);
            if (str_contains($this->alias->value, "\0")) {
                $out->spelled($out->quote($this->alias->value));
            } else {
                $out->name($this->alias, NameUse::Alias);
            }
        }
    }
}
