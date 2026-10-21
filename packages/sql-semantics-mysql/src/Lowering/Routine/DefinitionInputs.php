<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

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
final class DefinitionInputs
{
    /**
     * @param list<Name> $locals The enclosing routine parameters
     * @return array{ProgramProblem|ScopedLocalAssignment|NonUniqueTable, int}|null The first condition and its input boundary
     */
    public function first(Node $body, array $locals, int $end, ?SourceMap $sources): ?array
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
                $origin = $sources?->of($node);
                $boundary = $origin === null ? $end : $origin->offset + $origin->length;
                return [$problem, $problem instanceof NonUniqueTable ? $this->activation($body, $node, $boundary, $sources) : $boundary];
            }
        }
        return null;
    }

    /**
     * Includes CTE references reached before interpreting an earlier definition.
     */
    public function activation(Node $body, Node $subject, int $end, ?SourceMap $sources): int
    {
        foreach ((new ReadDependencies())->nodes($body) as $node) {
            $origin = $node instanceof TableReference ? $sources?->of($node) : null;
            if ($origin !== null) {
                $end = max($end, $origin->offset + $origin->length);
            }
            if ($node === $subject) {
                break;
            }
        }
        return $end;
    }
}
