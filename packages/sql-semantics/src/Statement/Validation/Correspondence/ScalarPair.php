<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\ScalarInput;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;

/**
 * A transient comparison task, never retained by a public semantic snapshot.
 * @visibility SqlSemantics
 */
final class ScalarPair
{
    /**
     * Records an explicit input, actual model operand, and required evaluation environment.
     */
    public function __construct(public readonly ScalarInput $input, public readonly ScalarExpression $actual, public readonly Scope|SqliteAliasScope $scope)
    {
    }
}
