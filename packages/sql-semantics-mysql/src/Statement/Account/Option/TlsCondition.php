<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One `SUBJECT|ISSUER|CIPHER 'value'` condition of a REQUIRE clause.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls.
 *
 * @visibility public
 * @example Holding a cipher condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition(\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute::Cipher, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('EDH-RSA-DES-CBC3-SHA')))->value->value // => 'EDH-RSA-DES-CBC3-SHA'
 */
final class TlsCondition implements Node
{
    use Snapshot;

    /**
     * @param TlsAttribute $attribute The property
     * @param Text $value The value the property must have
     */
    public function __construct(public readonly TlsAttribute $attribute, public readonly Text $value)
    {
    }

    /**
     * Writes the condition.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->attribute->value)->node($this->value);
    }
}
