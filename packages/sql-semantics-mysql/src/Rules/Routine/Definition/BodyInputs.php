<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Definition;

use SqlSemantics\Platform\MySql\Rules\Query\From\InputNames;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\ConditionBindings;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\LocalAssignments;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Orders fixed declaration-input constraints by their semantic occurrences.
 *
 * Ranges belong to the transient lowering map. Referencing a CTE activates its
 * input conditions at the reference, even though its definition appears earlier.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyInputs
{
    /**
     * @param list<Name> $locals The enclosing routine parameters
     * @return array{ProgramProblem|ScopedLocalAssignment|NonUniqueTable, Node}|null The first condition and its input boundary
     */
    public function first(Node $body, array $locals, ?SourceMap $sources = null): ?array
    {
        $candidates = [];
        $binding = (new ConditionBindings())->find($body, $locals);
        if ($binding !== null) {
            $candidates[spl_object_id($binding[1])] = $binding[0];
        }
        $scoped = (new LocalAssignments())->scoped($body, $locals);
        if ($scoped !== null) {
            $origin = $sources?->of($scoped);
            $candidates[spl_object_id($scoped)] = new ScopedLocalAssignment($origin?->offset, $origin === null ? null : $origin->offset + $origin->length);
        }
        $collision = (new InputNames())->problem($body);
        if ($collision?->relation !== null) {
            $candidates[spl_object_id($collision->relation)] = $collision;
        }
        foreach ((new ReadDependencies())->nodes($body, childrenFirst: true) as $node) {
            $problem = $candidates[spl_object_id($node)] ?? null;
            if ($problem !== null) {
                return [$problem, $problem instanceof NonUniqueTable ? $this->activation($body, $node, $sources) : $node];
            }
        }
        return null;
    }

    /**
     * Includes CTE references reached before interpreting an earlier definition.
     */
    public function activation(Node $body, Node $subject, ?SourceMap $sources): Node
    {
        $at = $subject;
        $origin = $sources?->of($subject);
        $end = $origin === null ? 0 : $origin->offset + $origin->length;
        foreach ((new ReadDependencies())->nodes($body) as $node) {
            $origin = $node instanceof TableReference ? $sources?->of($node) : null;
            if ($origin !== null) {
                if ($origin->offset + $origin->length > $end) {
                    $end = $origin->offset + $origin->length;
                    $at = $node;
                }
            }
            if ($node === $subject) {
                break;
            }
        }
        return $at;
    }
}
