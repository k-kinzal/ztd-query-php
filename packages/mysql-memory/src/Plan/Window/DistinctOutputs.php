<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\ProjectDistinct;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;

/**
 * Plans DISTINCT before the aggregate parents deferred by a window stage.
 *
 * @visibility MySqlMemory
 */
final class DistinctOutputs
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers a deferred projection path when an output must remain outside duplicate keys.
     *
     * @param list<Field> $fields The resolved output fields
     * @param list<Evaluable> $expressions The projection, including ordering values
     * @param list<Domain> $domains The resolved output domains
     */
    public function plan(Select $select, array $fields, AccessPath $input, array $expressions, array $domains, bool $hashed): ?AccessPath
    {
        $keys = [];
        $deferred = false;
        foreach ($fields as $position => $field) {
            if ((new OutputFields())->deferred($select, $field->expression)) {
                $deferred = true;
                continue;
            }
            $expression = $field->expression;
            if ($expression === null || !$this->planner->compiler->constancy($expression)->constant() || (new Walker())->find($expression, Query::class) !== []) {
                $keys[$position] = $domains[$position];
            }
        }

        return $deferred ? new ProjectDistinct($input, $expressions, $keys, $hashed) : null;
    }
}
