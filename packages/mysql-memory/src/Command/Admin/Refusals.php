<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use LogicException;
use MySqlMemory\Error\DiagnosticText;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
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
        return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong || $diagnostic instanceof StorageProblem || $diagnostic instanceof SpatialProblem || $diagnostic instanceof PriorityOutOfRange || $diagnostic instanceof RefusedSetting || $diagnostic instanceof NumberOutOfRange || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;
    }

    /**
     * Answers the server error of a problem of a statement whose text is given.
     *
     * @throws LogicException When the diagnostic has no administrative error mapping
     */
    public function error(Diagnostic $diagnostic, ?Node $statement, string $text, bool $legacy = false): SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair => $diagnostic->identical ? \MySqlMemory\Error\Family\AccountError::FactorIdentical->error() : \MySqlMemory\Error\Family\AccountError::FactorOrder->error(2, 3),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure => (new \MySqlMemory\Command\Account\Names($legacy ? \SqlSemantics\Contract\GrammarRelease::MySql5651 : \SqlSemantics\Contract\GrammarRelease::MySql847))->refusal($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong => SchemaError::TooLongIdentifier->error(\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName::decoded($diagnostic->name)),
            $diagnostic instanceof StorageProblem => match ($diagnostic->rule) {
                StorageRule::RepeatedOption => SchemaError::FilegroupOptionOnlyOnce->error($diagnostic->option === 'ENGINE' ? 'STORAGE ENGINE' : $diagnostic->option),
                StorageRule::WrongSize => SchemaError::WrongSizeNumber->error(),
                StorageRule::SizeOverflow => SchemaError::SizeOverflow->error(),
            },
            $diagnostic instanceof SpatialProblem => $this->spatial($diagnostic, $statement),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber => new SqlError(StatementError::ParseError, "Only integers allowed as number here near '" . $this->near($text, '/' . preg_quote($diagnostic->number->text, '/') . '/') . "' at line 1"),
            $diagnostic instanceof PriorityOutOfRange => $this->priority($diagnostic),
            $diagnostic instanceof RefusedSetting => $this->setting($diagnostic, $statement, $text, $legacy),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute => StatementError::DuplicateArgument->error($diagnostic->attribute->value),
            $diagnostic instanceof NumberOutOfRange => $diagnostic->error === 'ER_WRONG_VALUE' ? DataError::WrongValue->error($diagnostic->option, $diagnostic->number) : new SqlError(StatementError::ParseError, "Only integers allowed as number here near '" . $this->near($text, '/' . preg_quote($diagnostic->number, '/') . '/') . "' at line 1"),
            default => throw new LogicException('No administrative error mapping for ' . $diagnostic::class . ': ' . $diagnostic->message()),
        };
    }

    /**
     * Formats retained replication operands before considering statement-dependent diagnostics.
     */
    public function setting(RefusedSetting $problem, ?Node $statement, string $text, bool $legacy = false): SqlError
    {
        if ($problem->text !== null) {
            $uuid = $problem->error === ReplicationError::InvalidUuid;
            $value = (new Literals())->bytes($problem->text);

            return DataError::WrongValue->error($uuid ? 'UUID' : 'argument contains not-allowed LF', (new DiagnosticText())->bytes($uuid ? explode("\0", $value, 2)[0] : $value, 128, $legacy));
        }
        if ($problem->number !== null) {
            return $problem->error === ReplicationError::DelayOutOfRange ? $this->delay((new Literals())->number($problem->number), $legacy) : AdministrationError::BinlogIndexOutOfRange->error((new Literals())->number($problem->number));
        }

        return $this->replication($problem->error, $statement, $text, $legacy);
    }

    /**
     * Answers the error of a spatial reference system problem.
     */
    public function spatial(SpatialProblem $problem, ?Node $statement, ?\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand $command = null): SqlError
    {
        $attribute = $problem->attribute === null ? 'SRID' : $problem->attribute->value;
        $verb = $command->value ?? ($statement instanceof DropSpatialReference ? 'DROP' : ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference && $statement->orReplace ? 'CREATE OR REPLACE' : 'CREATE'));
        $subject = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable ? 'SRID' : $verb . ' SPATIAL REFERENCE SYSTEM';

        return match ($problem->rule) {
            SpatialRule::LineFeed => DataError::WrongValue->error('argument contains not-allowed LF', (new DiagnosticText())->bytes((string) $problem->value, 128)),
            SpatialRule::IdentifierOutOfRange => DataError::DataOutOfRange->error($problem->attribute === SpatialAttributeKind::Organization ? 'IDENTIFIED BY' : 'SRID', $subject),
            SpatialRule::IdentifierZero => SchemaError::SrsZeroUnmodifiable->error(),
            SpatialRule::RepeatedAttribute => SchemaError::SrsRepeatedAttribute->error($attribute),
            SpatialRule::MissingAttribute => SchemaError::SrsMissingAttribute->error($attribute),
            SpatialRule::BlankName => SchemaError::SrsBlankName->error(),
            SpatialRule::BlankOrganization => SchemaError::SrsBlankOrganization->error(),
            SpatialRule::ControlCharacter => SchemaError::SrsInvalidCharacter->error($attribute),
            SpatialRule::TooLong => SchemaError::SrsAttributeTooLong->error($attribute, (string) (self::LIMITS[$attribute] ?? 0)),
        };
    }

    /**
     * Answers the error of a thread priority outside the range of the type of a new resource group.
     */
    public function priority(PriorityOutOfRange $problem): SqlError
    {
        $system = $problem->kind === ResourceGroupKind::System;

        return AdministrationError::InvalidThreadPriority->error((string) $problem->priority->value(), $system ? 'System' : 'User', $problem->name->value, $system ? '-20' : '0', $system ? '0' : '19');
    }

    /**
     * Answers the error of a replication setting the server refuses while it parses the statement.
     */
    public function replication(ReplicationError $error, ?Node $statement, string $text, bool $legacy = false): SqlError
    {
        return match ($error) {
            ReplicationError::LineFeed => DataError::WrongValue->error('argument contains not-allowed LF', (new DiagnosticText())->bytes($this->lineFeed($statement), 128, $legacy)),
            ReplicationError::PasswordTooLong => AdministrationError::SourcePasswordTooLong->error(),
            ReplicationError::GroupPasswordTooLong => AdministrationError::GroupPasswordTooLong->error(),
            ReplicationError::GroupUserMissing => AdministrationError::GroupUserMissing->error(),
            ReplicationError::GroupUserEmpty => AdministrationError::GroupUserEmpty->error(),
            ReplicationError::DelayOutOfRange => $this->delay($this->option($statement, SourceOptionKind::Delay), $legacy),
            ReplicationError::HeartbeatOutOfRange => AdministrationError::HeartbeatOutOfRange->error('4294967'),
            ReplicationError::RowFormatValue => AdministrationError::RowFormatValue->error($this->option($statement, SourceOptionKind::RequireRowFormat)),
            ReplicationError::SwitchValue => $this->switch($statement, $text),
            ReplicationError::FractionalNumber => new SqlError(StatementError::ParseError, "Only integers allowed as number here near '" . $this->near($text, '/[0-9]*\.[0-9]/') . "' at line 1"),
            ReplicationError::InvalidUuid => DataError::WrongValue->error('UUID', (new DiagnosticText())->bytes(explode("\0", $this->option($statement, SourceOptionKind::AssignGtidsToAnonymousTransactions), 2)[0], 128)),
            ReplicationError::UntilCondition => AdministrationError::BadReplicaUntil->error(),
            ReplicationError::ApplierWithCredentials => AdministrationError::SqlThreadWithCredentials->error(),
            ReplicationError::FileNumberOutOfRange => AdministrationError::BinlogIndexOutOfRange->error($this->first($statement)),
            ReplicationError::WildPattern => AdministrationError::WildTableFilterPattern->error(),
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

        return new SqlError(StatementError::ParseError, 'You have an error in your CHANGE REPLICATION SOURCE syntax; ' . $kind->value . " only accepts values 0 or 1 near '" . $near . "' at line 1");
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
    /**
     * Formats a retained source delay in the vocabulary of the selected release.
     */
    public function delay(string $value, bool $legacy): SqlError
    {
        return $legacy
            ? new SqlError(AdministrationError::SourceDelayOutOfRange, 'The requested value ' . $value . ' for the master delay exceeds the maximum 2147483647')
            : AdministrationError::SourceDelayOutOfRange->error($value, '2147483647');
    }

}
