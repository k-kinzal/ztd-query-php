<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A resource-group identifier that exceeds its 64-byte limit after conversion.
 *
 * @visibility public
 * @example Describing a refused name
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong(new \SqlSemantics\Statement\Identifier\Name(str_repeat('a', 65))))->message() // => "Identifier name 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' is too long"
 */
final class ResourceGroupNameTooLong implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The full decoded operand before character conversion
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Describes the refused identifier without truncating the retained operand.
     */
    public function message(): string
    {
        return "Identifier name '" . ResourceGroupName::decoded($this->name) . "' is too long";
    }
}
