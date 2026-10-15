<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Rules\Replication\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Formats source-number input refusals at their retained operand position.
 *
 * Source SQL is used only for the server's syntax excerpt at this boundary.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceNumberInput
{
    /**
     * Returns the source-option error, including its original numeric spelling.
     */
    public function error(RefusedSourceNumber $problem, string $sql): SqlError
    {
        $number = $problem->number;
        if ($problem->kind === SourceOptionKind::RequireRowFormat) {
            return AdministrationError::RowFormatValue->error(($number->hexadecimal ? '0x' : '') . $number->text);
        }
        $near = mb_strcut(substr($sql, $problem->offset), 0, 80, 'UTF-8');
        $message = (new Magnitudes())->fractional($number) ? 'Only integers allowed as number here' : ($problem->kind === SourceOptionKind::GtidOnly ? 'You have an error in your CHANGE REPLICATION SOURCE syntax; GTID_ONLY only accepts values 0 or 1' : 'You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use');
        $line = substr_count(substr($sql, 0, $problem->offset), "\n") + 1;

        return new SqlError(StatementError::ParseError, $message . " near '" . $near . "' at line " . $line);
    }
}
