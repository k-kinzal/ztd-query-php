<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Rules\Call\NamedConstant;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * Preserves the additional condition NAME_CONST reports after its value's collation fails.
 *
 * The semantic diagnostic identifies the exact failing occurrence, so another expression
 * with the same collation does not acquire NAME_CONST's reserved-syntax condition.
 * Verified on MySQL 5.6.51, 5.7.44 and 8.4.7 through SHOW WARNINGS. MySQL 9.1
 * reports only the original condition.
 *
 * @visibility MySqlMemory
 */
final class NamedConstants
{
    /**
     * Adds the reserved-syntax condition only when this error belongs to NAME_CONST's value.
     */
    public function following(SqlError $error, Diagnostic $diagnostic, Node $statement, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): SqlError
    {
        if ($release === \SqlSemantics\Contract\GrammarRelease::MySql910 || !$diagnostic instanceof CollationMismatch || $diagnostic->expression === null) {
            return $error;
        }
        foreach ((new Walker())->find($statement, FunctionCall::class) as $call) {
            if ($call->schema === null && strcasecmp($call->name->value, 'NAME_CONST') === 0 && count($call->arguments) === 2 && (new NamedConstant())->unwrapped($call->arguments[1]->expression) === $diagnostic->expression) {
                return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), [...$error->following, [StatementError::ReservedSyntax->value, StatementError::ReservedSyntax->message('NAME_CONST')]]);
            }
        }

        return $error;
    }
}
