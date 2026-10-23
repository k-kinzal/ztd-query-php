<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\FieldConstants;
use MySqlMemory\Evaluation\Compile\FieldStrings;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Preserves legacy temporal LIKE preparation before pattern evaluation.
 *
 * MySQL 5.x reads constant strings against date operands while preparing LIKE.
 * MySQL 5.6 also refuses particular temporal/text collation combinations before
 * reading the pattern. These decisions use resolved domains and constancy,
 * independently of the original SQL spelling.
 * Verified with constants, columns and both operand orders on 5.6, 5.7 and 8.4.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class LegacyPatterns
{
    /**
     * @param Compiler $compiler The current statement compiler
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Reads temporal comparison constants once, then checks legacy collation constraints.
     *
     * @throws SqlError When the legacy operands have incompatible collations
     */
    public function prepare(Like $node, Evaluable $operand, Evaluable $pattern): void
    {
        $this->constant($node->operand, $operand, $node->pattern, $pattern);
        $dateSubject = $this->constant($node->pattern, $pattern, $node->operand, $operand);
        if ($this->compiler->settings->release() !== GrammarRelease::MySql5651) {
            return;
        }
        $left = $operand->domain();
        $right = $pattern->domain();
        $incompatible = $left->kind === Kind::String && !in_array($left->collation->charset->name, ['binary', 'latin1'], true)
            && ($right->kind === Kind::Time || $dateSubject === false);
        if ($incompatible) {
            $problem = new IllegalCollationMix([[$left->collation->nameIn(GrammarRelease::MySql5651), $left->coercibility], ['latin1_swedish_ci', Coercibility::Numeric]], 'like');
            throw new SqlError(DataError::CantAggregateTwoCollations, $problem->message());
        }
        if ($left->kind === Kind::Time && $right->kind === Kind::String && !in_array($right->collation->charset->name, ['binary', 'latin1'], true) && !$this->compiler->constancy($node->pattern)->constant()) {
            throw DataError::CantAggregateCollations->error('like');
        }
    }

    /**
     * Checks a constant string against a date-bearing operand before row evaluation.
     * A valid date may have trailing text: that warns but still participates in
     * collation aggregation. LIKE continues to match the original string.
     *
     * @return bool|null Whether the string holds a date, or null when no date conversion applies
     */
    public function constant(Scalar $temporalNode, Evaluable $temporal, Scalar $textNode, Evaluable $text): ?bool
    {
        $kind = $temporal->domain()->kind;
        if (!in_array($kind, [Kind::Date, Kind::DateTime], true) || $text->domain()->kind !== Kind::String || !$this->compiler->constancy($textNode)->constant()) {
            return null;
        }
        $value = $text->evaluate(new Frame($this->compiler->connection->context));
        if ($value === null) {
            return true;
        }
        $parts = Temporal::scanDateTime((string) $value);
        $modes = $this->compiler->connection->context->modes;
        $valid = $parts !== null && $parts[3] <= 23 && $parts[4] <= 59 && $parts[5] <= 59 && Temporal::accepted($parts[0], $parts[1], $parts[2], $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'));
        if (!$valid || $parts[8] !== '') {
            $type = $kind === Kind::Date ? 'date' : 'datetime';
            $column = (new FieldConstants($this->compiler))->column($temporalNode);
            if ($column !== null) {
                (new FieldStrings($this->compiler))->warn($type, (string) $value, $column);
            } else {
                $this->compiler->connection->context->diagnostics->warning(DataError::TruncatedWrongValue, DataError::TruncatedWrongValue->message($type, (string) $value));
            }
        }

        return $valid;
    }
}
