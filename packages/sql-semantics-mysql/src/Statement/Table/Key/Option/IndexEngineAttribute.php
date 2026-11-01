<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * The ENGINE_ATTRIBUTE or SECONDARY_ENGINE_ATTRIBUTE of an index (MySQL 8.0.21 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) ENGINE_ATTRIBUTE \'{}\')');
 *     $create->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute // => true
 */
final class IndexEngineAttribute implements IndexOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $attribute The attribute document
     * @param bool $secondary Whether it is the SECONDARY_ENGINE_ATTRIBUTE
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     */
    public function __construct(public readonly Text $attribute, public readonly bool $secondary = false, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null)
    {
        \SqlSemantics\Platform\MySql\Rules\Text\Json\Attribute::check($attribute);
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->secondary ? 'SECONDARY_ENGINE_ATTRIBUTE' : 'ENGINE_ATTRIBUTE');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        $out->node($this->attribute);
    }
}
