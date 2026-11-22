<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ATTRIBUTE clause whose string is not a JSON object.
 *
 * The server rejects the statement with ER_INVALID_USER_ATTRIBUTE_JSON.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-comments-attributes.
 *
 * @visibility SqlSemantics
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute('[1]'))->message() // => 'The user attribute [1] is not a JSON object (ER_INVALID_USER_ATTRIBUTE_JSON).'
 */
final class InvalidUserAttribute implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $text The attribute string as decoded
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'The user attribute ' . $this->text . ' is not a JSON object (ER_INVALID_USER_ATTRIBUTE_JSON).';
    }
}
