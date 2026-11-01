<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Math\Tally;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Typing\Domain;

/**
 * Compiles a numeric use without changing the expression's independently selected type.
 * Legacy GREATEST and LEAST compare their arguments in the consuming numeric context;
 * modern releases first compute their ordinary result and then convert it.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/comparison-operators.html.
 *
 * @visibility MySqlMemory
 */
final class NumericReads
{
    /**
     * @param bool $legacy Whether expression evaluation follows MySQL 5.6 or 5.7
     */
    public function __construct(public readonly bool $legacy)
    {
    }

    /**
     * Specializes an extremum read in double precision, retaining string-producing
     * boundaries and stored fields. Arguments are still evaluated once per read.
     */
    public function double(Evaluable $operand): Evaluable
    {
        if (!$this->legacy) {
            return $operand;
        }
        $source = $operand;
        while ($source instanceof Retyped) {
            $source = $source->evaluable;
        }
        if ($source instanceof Tally) {
            return new Tally($this->double($source->argument));
        }
        if (!$source instanceof Call || !in_array($source->routine->name, ['GREATEST', 'LEAST'], true)) {
            return $operand;
        }

        return new Call($source->routine, array_map($this->double(...), $source->arguments), Domain::double(23)->withNullable($operand->domain()->nullable), $source->text);
    }
}
