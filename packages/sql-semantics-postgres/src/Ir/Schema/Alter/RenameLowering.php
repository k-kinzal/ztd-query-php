<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;

/**
 * Converts supported PostgreSQL ALTER TABLE naming operations into complete values.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RenameLowering
{
    /**
     * Publishes the requested table or column name change.
     */
    public function lower(BoundStatement $operation, Rename $statement): RenameTable|RenameColumn|RenameSequence
    {
        if ($statement->kind === ObjectKind::Sequence) {
            return (new RenameSequenceLowering())->lower($operation, $statement);
        }
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native
            && $context->relationNames === Comparison::Sensitive && $context->columnNames === Comparison::Sensitive,
            'ALTER TABLE RENAME requires the native naming profile.'
        );
        $reference = $statement->object;
        $member = $statement->member;
        Check::input($statement->kind === ObjectKind::Table && $reference instanceof RelationTarget
            && ($member === null || $member->part === RenamedPart::Column), 'The checked rename needs a table or column operation.');
        [$target, $table] = (new RenameTargetLowering())->lower($operation, $reference, $statement->ifExists);
        $destination = new Identifier($statement->newName->value);
        $release = $context->profile->grammar;
        if ($member === null) {
            return new RenameTable($target, $destination, $statement->ifExists, $release);
        }
        if ($table !== null) {
            Check::input(
                $table->complete && count($table->matchingColumns($member->name->value, Comparison::Sensitive)) === 1
                && $table->matchingColumns($destination->value, Comparison::Sensitive) === [],
                'A column rename requires a unique declared source column and an unused destination.'
            );
        }

        return new RenameColumn($target, new Identifier($member->name->value), $destination, $reference->relation->only, $statement->ifExists, $release);
    }
}
