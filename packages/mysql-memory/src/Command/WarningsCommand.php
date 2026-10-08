<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrorCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarningCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes SHOW WARNINGS and SHOW ERRORS: the conditions of the diagnostics area.
 *
 * The Level and Message columns are text of 7 and 512 characters in the character set of the
 * results, utf8mb3 when it has none; Code is an unsigned INT of 5 digits. SHOW COUNT(*) WARNINGS
 * and SHOW COUNT(*) ERRORS answer the number of conditions, as @@session.warning_count and
 * @@session.error_count do. A LIMIT operand naming a variable is ER_SP_UNDECLARED_VAR, added to
 * the conditions the area holds (verified on live 8.0, 8.4 and 9.1 servers).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 *
 * @visibility MySqlMemory
 */
final class WarningsCommand implements Command
{
    /**
     * Answers false: the statement reads the area the last statement left.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return false;
    }

    /**
     * Answers the conditions, or their number for SHOW COUNT(*) WARNINGS and SHOW COUNT(*) ERRORS.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $bounded = $statement instanceof ShowWarnings || $statement instanceof ShowErrors ? $statement->limit : null;
        foreach ($bounded instanceof RowLimit ? [$bounded->offset, $bounded->count] : [] as $operand) {
            if ($operand instanceof ProgramVariable) {
                throw ProgramError::UndeclaredVariable->error($operand->name->value);
            }
        }
        $errors = $statement instanceof ShowErrors || $statement instanceof ShowErrorCount;
        $rows = [];
        foreach ($session->diagnostics->conditions as [$level, $code, $message]) {
            if (!$errors || $level === 'Error') {
                $rows[] = [$level, (string) $code, $message];
            }
        }
        if ($statement instanceof ShowWarningCount || $statement instanceof ShowErrorCount) {
            $name = $errors ? '@@session.error_count' : '@@session.warning_count';

            return new ResultSet([new ResultColumn($name, Field::LongLong, 21, 0, ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value, 63)], [[(string) count($rows)]]);
        }
        $limit = $statement instanceof ShowWarnings || $statement instanceof ShowErrors ? $statement->limit : null;
        if ($limit instanceof RowLimit) {
            $rows = array_slice($rows, $this->bound($limit->offset) ?? 0, $this->bound($limit->count));
        }
        $results = $session->variables->read('character_set_results');
        $charset = (is_string($results) ? Charset::named($results) : null) ?? Charset::known('utf8mb3');
        $collation = $charset->defaultCollation(GrammarRelease::MySql847)->id;
        $columns = [
            new ResultColumn('Level', Field::VarString, 7 * $charset->maxLength, 31, ColumnFlag::NotNull->value, $collation),
            new ResultColumn('Code', Field::Long, 5, 0, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value, 63),
            new ResultColumn('Message', Field::VarString, 512 * $charset->maxLength, 31, ColumnFlag::NotNull->value, $collation),
        ];

        return new ResultSet($columns, $rows);
    }

    /**
     * Answers the value of a bound of LIMIT, an unsigned integer literal; none counts from the first condition.
     */
    public function bound(?Scalar $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof NumberLiteral ? (int) $value->text : 0;
    }
}
