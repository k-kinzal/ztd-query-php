<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A column attribute written as keywords alone, such as NOT NULL, AUTO_INCREMENT, PRIMARY KEY or INVISIBLE.
 *
 * `KEY` alone means `PRIMARY KEY` and `UNIQUE KEY` means `UNIQUE`; the
 * attribute is written in the full form and the short form respectively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility public
 * @example Reading a keyword attribute
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute(\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::AutoIncrement))->keyword->value // => 'AUTO_INCREMENT'
 */
final class KeywordAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param ColumnKeyword $keyword The attribute
     */
    public function __construct(public readonly ColumnKeyword $keyword)
    {
    }

    /**
     * Derives nothing: the attribute holds no expression.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->keyword->value));
    }
}
