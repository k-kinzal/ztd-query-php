<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A string classification inferred before its length and collation are resolved.
 *
 * This descriptor belongs to expression facts. It is not a written type and cannot
 * occupy a column, parameter, variable or return declaration. A resolved expression
 * instead carries its complete Domain.
 *
 * @visibility public
 * @example Naming an inferred binary string family
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\StringFamily(true))->name() // => 'VARBINARY'
 */
final class StringFamily implements TypeDescriptor
{
    use \SqlSemantics\Statement\Snapshot;

    /**
     * @param bool $binary Whether the inferred value consists of bytes
     * @param bool $national Whether its character set is the national character set
     * @param CharsetAttribute|null $charset The known character set attribute
     */
    public function __construct(public readonly bool $binary = false, public readonly bool $national = false, public readonly ?CharsetAttribute $charset = null)
    {
        Check::input(!$binary || (!$national && $charset === null), 'A binary string family has no character set attributes.');
        Check::input(!$national || $charset === null || $charset->form === CharsetForm::Binary, 'A national string family takes the BINARY attribute alone.');
    }

    /**
     * Names the family without inventing a declared length.
     */
    public function name(): string
    {
        return $this->binary ? 'VARBINARY' : ($this->national ? 'NVARCHAR' : 'VARCHAR');
    }
}
