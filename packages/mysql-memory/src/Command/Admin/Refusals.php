<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * Answers the server errors of the problems SQL Semantics finds while it reads an administrative statement: a tablespace option, a spatial reference system attribute, a resource group priority or thread id, or a replication setting the server refuses as it parses.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class Refusals
{
    /**
     * The character limits of the spatial reference system attributes.
     */
    public const LIMITS = ['NAME' => 80, 'DEFINITION' => 4096, 'ORGANIZATION' => 256, 'DESCRIPTION' => 2048];

    /**
     * Tells whether a diagnostic is one of the problems this class answers the error of.
     */
    public static function handles(Diagnostic $diagnostic): bool
    {
        return $diagnostic instanceof StorageProblem || $diagnostic instanceof SpatialProblem || $diagnostic instanceof PriorityOutOfRange || $diagnostic instanceof RefusedSetting || $diagnostic instanceof NumberOutOfRange;
    }

    /**
     * Answers the server error of a problem of a statement whose text is given.
     */
    public function error(Diagnostic $diagnostic, ?Node $statement, string $text): SqlError
    {
        return match (true) {
            $diagnostic instanceof StorageProblem => match ($diagnostic->rule) {
                StorageRule::RepeatedOption => ErrorCode::FilegroupOptionOnlyOnce->error($diagnostic->option === 'ENGINE' ? 'STORAGE ENGINE' : $diagnostic->option),
                StorageRule::WrongSize => ErrorCode::WrongSizeNumber->error(),
                StorageRule::SizeOverflow => ErrorCode::SizeOverflow->error(),
            },
            $diagnostic instanceof SpatialProblem => $this->spatial($diagnostic, $statement),
            $diagnostic instanceof PriorityOutOfRange => $this->priority($diagnostic, $statement),
            $diagnostic instanceof RefusedSetting => $this->replication($diagnostic->error, $statement, $text),
            $diagnostic instanceof NumberOutOfRange => $diagnostic->error === 'ER_WRONG_VALUE' ? ErrorCode::WrongValue->error($diagnostic->option, $diagnostic->number) : new SqlError(ErrorCode::ParseError, "Only integers allowed as number here near '" . $this->near($text, '/' . preg_quote($diagnostic->number, '/') . '/') . "' at line 1"),
            default => new SqlError(ErrorCode::UnknownError, $diagnostic->message()),
        };
    }

    /**
     * Answers the error of a spatial reference system problem.
     */
    public function spatial(SpatialProblem $problem, ?Node $statement): SqlError
    {
        $attribute = $problem->attribute === null ? 'SRID' : $problem->attribute->value;

        return match ($problem->rule) {
            SpatialRule::IdentifierOutOfRange => ErrorCode::DataOutOfRange->error($problem->attribute === SpatialAttributeKind::Organization ? 'IDENTIFIED BY' : 'SRID', ($statement instanceof DropSpatialReference ? 'DROP' : 'CREATE') . ' SPATIAL REFERENCE SYSTEM'),
            SpatialRule::IdentifierZero => ErrorCode::SrsZeroUnmodifiable->error(),
            SpatialRule::RepeatedAttribute => ErrorCode::SrsRepeatedAttribute->error($attribute),
            SpatialRule::MissingAttribute => ErrorCode::SrsMissingAttribute->error($attribute),
            SpatialRule::BlankName => ErrorCode::SrsBlankName->error(),
            SpatialRule::BlankOrganization => ErrorCode::SrsBlankOrganization->error(),
            SpatialRule::ControlCharacter => ErrorCode::SrsInvalidCharacter->error($attribute),
            SpatialRule::TooLong => ErrorCode::SrsAttributeTooLong->error($attribute, (string) (self::LIMITS[$attribute] ?? 0)),
        };
    }

    /**
     * Answers the error of a thread priority outside the range of the type of a new resource group.
     */
    public function priority(PriorityOutOfRange $problem, ?Node $statement): SqlError
    {
        $system = $problem->type === 'SYSTEM';
        $name = $statement instanceof CreateResourceGroup ? $statement->name->value : '';
        $value = $statement instanceof CreateResourceGroup ? (string) (new ResourceGroupCommand())->priority($statement->priority) : $problem->priority;

        return ErrorCode::InvalidThreadPriority->error($value, $system ? 'System' : 'User', $name, $system ? '-20' : '0', $system ? '0' : '19');
    }

    /**
     * Answers the error of a replication setting the server refuses while it parses the statement.
     */
    public function replication(ReplicationError $error, ?Node $statement, string $text): SqlError
    {
        return match ($error) {
            ReplicationError::LineFeed => ErrorCode::WrongValue->error('argument contains not-allowed LF', $this->lineFeed($statement)),
            ReplicationError::PasswordTooLong => ErrorCode::SourcePasswordTooLong->error(),
            ReplicationError::GroupPasswordTooLong => ErrorCode::GroupPasswordTooLong->error(),
            ReplicationError::DelayOutOfRange => ErrorCode::SourceDelayOutOfRange->error($this->option($statement, SourceOptionKind::Delay), '2147483647'),
            ReplicationError::HeartbeatOutOfRange => ErrorCode::HeartbeatOutOfRange->error('4294967'),
            ReplicationError::RowFormatValue => ErrorCode::RowFormatValue->error($this->option($statement, SourceOptionKind::RequireRowFormat)),
            ReplicationError::SwitchValue => $this->switch($statement, $text),
            ReplicationError::FractionalNumber => new SqlError(ErrorCode::ParseError, "Only integers allowed as number here near '" . $this->near($text, '/[0-9]*\.[0-9]/') . "' at line 1"),
            ReplicationError::InvalidUuid => ErrorCode::WrongValue->error('UUID', $this->option($statement, SourceOptionKind::AssignGtidsToAnonymousTransactions)),
            ReplicationError::UntilCondition => ErrorCode::BadReplicaUntil->error(),
            ReplicationError::ApplierWithCredentials => ErrorCode::SqlThreadWithCredentials->error(),
            ReplicationError::FileNumberOutOfRange => ErrorCode::BinlogIndexOutOfRange->error($this->first($statement)),
            ReplicationError::WildPattern => ErrorCode::WildTableFilterPattern->error(),
        };
    }

    /**
     * Answers the syntax error of a switch option written with a value other than 0 or 1.
     */
    public function switch(?Node $statement, string $text): SqlError
    {
        $kind = SourceOptionKind::GtidOnly;
        foreach ($statement === null ? [] : (new Walker())->find($statement, SourceOption::class) as $option) {
            $value = $option->value instanceof Numeral ? (new Literals())->number($option->value) : null;
            if (($option->kind === SourceOptionKind::GtidOnly || $option->kind === SourceOptionKind::ConnectionAutoFailover) && $value !== '0' && $value !== '1') {
                $kind = $option->kind;
                break;
            }
        }
        $near = $this->near($text, '/\b' . $kind->value . '\s*=\s*/i', true);

        return new SqlError(ErrorCode::ParseError, 'You have an error in your CHANGE REPLICATION SOURCE syntax; ' . $kind->value . " only accepts values 0 or 1 near '" . $near . "' at line 1");
    }

    /**
     * Answers the text of a statement from the first match of a pattern, or from its end, cut to 80 bytes as the server quotes it.
     */
    public function near(string $text, string $pattern, bool $after = false): string
    {
        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }
        $at = $match[0][1] + ($after ? strlen($match[0][0]) : 0);

        return mb_strcut(substr($text, $at), 0, 80, 'UTF-8');
    }

    /**
     * Answers the first text of a statement that holds a line feed.
     */
    public function lineFeed(?Node $statement): string
    {
        foreach ($statement === null ? [] : (new Walker())->find($statement, Text::class) as $text) {
            $bytes = (new Literals())->bytes($text);
            if (str_contains($bytes, "\n")) {
                return $bytes;
            }
        }

        return '';
    }

    /**
     * Answers the value written for a source option, as written.
     */
    public function option(?Node $statement, SourceOptionKind $kind): string
    {
        foreach ($statement === null ? [] : (new Walker())->find($statement, SourceOption::class) as $option) {
            if ($option->kind === $kind) {
                return match (true) {
                    $option->value instanceof Text => (new Literals())->bytes($option->value),
                    $option->value instanceof Numeral => (new Literals())->number($option->value),
                    default => '',
                };
            }
        }

        return '';
    }

    /**
     * Answers the binary log file number of RESET BINARY LOGS AND GTIDS TO.
     */
    public function first(?Node $statement): string
    {
        foreach ($statement === null ? [] : (new Walker())->find($statement, ResetBinaryLogs::class) as $reset) {
            if ($reset->first !== null) {
                return (new Literals())->number($reset->first);
            }
        }

        return '0';
    }
}
