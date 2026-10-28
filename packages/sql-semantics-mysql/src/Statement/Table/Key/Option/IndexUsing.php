<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Rendering\Output;

/**
 * A USING clause written after the key parts. TYPE is a deprecated synonym of USING.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) USING HASH)');
 *     $create->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing // => true
 */
final class IndexUsing implements IndexOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param IndexAlgorithm $algorithm The index structure
     * @param bool $typeKeyword Whether TYPE introduces the algorithm instead of USING
     */
    public function __construct(public readonly IndexAlgorithm $algorithm, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $typeKeyword = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->typeKeyword ? 'TYPE' : 'USING', $this->algorithm->value);
    }
}
