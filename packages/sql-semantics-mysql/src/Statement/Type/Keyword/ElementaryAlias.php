<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;

/**
 * The written synonyms of a elementary type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\ElementaryAlias::Boolean->value // => 'BOOLEAN'
 */
enum ElementaryAlias: string
{
    case Boolean = 'BOOLEAN';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): ElementaryKind
    {
        return match ($this) {
            self::Boolean => ElementaryKind::Boolean,
        };
    }
}
