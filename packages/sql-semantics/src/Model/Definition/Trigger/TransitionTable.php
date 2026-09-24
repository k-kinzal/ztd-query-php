<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A REFERENCING name under which an AFTER trigger sees the old or new rows of its statement.
 * @visibility public
 * @example Reading a transition table
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE TRIGGER audit AFTER DELETE ON t REFERENCING OLD TABLE AS gone EXECUTE FUNCTION log_rows()');
 *     $statement->transitions[0]->version // => \SqlSemantics\Model\Trigger\RowVersion::Old
 *     $statement->transitions[0]->name // => 'gone'
 *     new \SqlSemantics\Model\Definition\Trigger\TransitionTable(\SqlSemantics\Model\Trigger\RowVersion::New, '') // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TransitionTable
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly RowVersion $version, public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A transition table requires a nonempty name.');
        }
    }
}
