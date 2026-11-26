<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute;

/**
 * A REQUIRE clause that names SUBJECT, ISSUER or CIPHER more than once.
 *
 * The server rejects the statement while parsing it with ER_DUP_ARGUMENT.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls.
 *
 * @visibility SqlSemantics
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute(\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute::Issuer))->message() // => 'ISSUER is required more than once (ER_DUP_ARGUMENT).'
 */
final class RepeatedTlsAttribute implements Diagnostic
{
    use Snapshot;

    /**
     * @param TlsAttribute $attribute The property named again
     */
    public function __construct(public readonly TlsAttribute $attribute)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->attribute->value . ' is required more than once (ER_DUP_ARGUMENT).';
    }
}
