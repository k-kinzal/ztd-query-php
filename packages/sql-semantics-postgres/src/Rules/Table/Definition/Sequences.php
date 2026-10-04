<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives CREATE SEQUENCE and the options of a sequence.
 *
 * Rule: PG-SEQUENCE-001. A sequence is a relation that can be read: "the
 * sequence's last_value, log_cnt and is_called" are its columns (bigint,
 * bigint, boolean), never NULL, with the system columns. A temporary sequence
 * with an unqualified name belongs to `pg_temp`; inside CREATE SCHEMA an
 * unqualified sequence belongs to that schema. SEQUENCE NAME is accepted only
 * for the sequence of an identity column and is reported in CREATE and ALTER
 * SEQUENCE ("invalid sequence option SEQUENCE NAME").
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Sequences
{
    /**
     * Derives the options and provides the declaration; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function create(CreateSequence $sequence, Derivation $derivation, ?Name $schema): void
    {
        $this->options($sequence->options, $derivation);
        $columns = [
            new Column(new Name('last_value'), Builtin::Int8, Nullability::NotNull),
            new Column(new Name('log_cnt'), Builtin::Int8, Nullability::NotNull),
            new Column(new Name('is_called'), Builtin::Bool, Nullability::NotNull),
        ];
        $name = (new QueryTables())->name($sequence->name, $sequence->persistence, $schema);
        $derivation->declare(new Table($name, $derivation->context->profile, $columns, (new SystemColumns())->implicit()));
    }

    /**
     * Derives the options of CREATE or ALTER SEQUENCE and reports SEQUENCE NAME.
     *
     * @param list<SequenceOption> $options
     */
    public function options(array $options, Derivation $derivation): void
    {
        foreach ($options as $option) {
            $option->deriveClause($derivation, $derivation->environment());
            if ($option instanceof SequenceReference && !$option->owned) {
                $derivation->report(new DefinitionProblem(DefinitionRule::InvalidSequenceOption, new Name('SEQUENCE NAME')));
            }
        }
    }
}
