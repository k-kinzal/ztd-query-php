<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An explicit secondary-index name that cannot identify an index.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_wrong_name_for_index.
 *
 * @visibility public
 * @example Reading the retained index-name condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName(new \SqlSemantics\Statement\Identifier\Name('PRIMARY')))->message() // => "Incorrect index name 'PRIMARY'."
 */
final class InvalidIndexName implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The rejected occurrence, preserved for ownership and error ordering
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Describes the name independently of table existence or key-column availability.
     */
    public function message(): string
    {
        return "Incorrect index name '" . $this->name->value . "'.";
    }
}
