<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result field of SHOW PARSE_TREE.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\ParseTreeField::Tree->label() // => 'Parse_tree'
 */
enum ParseTreeField: string implements MetadataField
{
    use TextField;

    case Tree = 'Parse_tree';

    /**
     * The parse tree is returned as JSON text.
     */
    public function type(): string
    {
        return 'json';
    }
}
