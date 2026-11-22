<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

/**
 * The check option of an updatable view: rows written through the view must satisfy its condition.
 *
 * Mirrors `ViewCheckOption`. WITH CHECK OPTION without a word is CASCADED;
 * the spelling is kept because the grammar keeps it.
 * Source: https://www.postgresql.org/docs/17/sql-createview.html.
 *
 * @visibility SqlSemantics
 */
enum CheckOption: string
{
    case Unqualified = '';
    case Cascaded = 'CASCADED';
    case Local = 'LOCAL';

    /**
     * Tells whether the conditions of the views below are checked too.
     */
    public function cascaded(): bool
    {
        return $this !== self::Local;
    }

    /**
     * Answers the keywords of the clause.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return $this === self::Unqualified ? ['WITH', 'CHECK', 'OPTION'] : ['WITH', $this->value, 'CHECK', 'OPTION'];
    }
}
