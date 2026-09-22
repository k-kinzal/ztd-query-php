<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

/**
 * A classified SQL request whose operands cannot form a valid semantic operation.
 * @visibility public
 * @example Inspecting an invalid assignment
 *     \SqlSemantics\Model\Validation\InputViolation::AssignmentWidth->value // => 'assignment-column-count'
 */
enum InputViolation: string
{
    case DeleteTarget = 'delete-target';
    case ExtractionField = 'extraction-field';
    case QueryOperand = 'query-operand';
    case FunctionArity = 'function-arity';
    case ReindexOption = 'reindex-option';
    case ConcurrentSystemReindex = 'concurrent-system-reindex';
    case OrderedSetWindow = 'ordered-set-window';
    case CteColumnCount = 'cte-column-count';
    case DuplicateCte = 'duplicate-cte';
    case DefaultContext = 'default-context';
    case ForeignKeyWidth = 'foreign-key-column-count';
    case ConcurrentIndexDrop = 'invalid-concurrent-index-drop';
    case JsonOption = 'json-option';
    case XmlOption = 'xml-option';
    case CursorOptions = 'cursor-options';
    case ScalarQueryWidth = 'scalar-query-width';
    case ComparisonWidth = 'comparison-width';
    case JoinedMutationPagination = 'joined-mutation-pagination';
    case TriggerQualifiedTarget = 'qualified-trigger-target';
    case TriggerIndexHint = 'trigger-index-hint';
    case AddedColumnKey = 'added-column-key';
    case MultiplePrimaryKeys = 'multiple-primary-keys';
    case OutputPosition = 'invalid-output-position';
    case ExplainOption = 'invalid-explain-option';
    case ExplainSetting = 'invalid-explain-setting';
    case ExplainCombination = 'invalid-explain-combination';
    case AssignmentWidth = 'assignment-column-count';
    case TupleSource = 'tuple-source';
    case InsertWidth = 'insert-column-count';
    case ValuesWidth = 'values-column-count';
    case SetWidth = 'set-column-count';
    case AlterAlgorithm = 'invalid-alter-algorithm';
    case AlterLock = 'invalid-alter-lock';
    /**
     * Describes the operand invariant identified by this diagnosis.
     */
    public function message(): string
    {
        return [
            'delete-target' => 'A MySQL DELETE destination requires a named table reference.',
            'extraction-field' => 'EXTRACT requires a field defined by the database language.',
            'query-operand' => 'A query operand requires a SELECT, VALUES, or TABLE operation.',
            'function-arity' => 'The function or conditional operation requires its declared number of arguments.',
            'reindex-option' => 'REINDEX requires a known option and a value in its declared domain.',
            'concurrent-system-reindex' => 'System-table indexes cannot be rebuilt concurrently.',
            'ordered-set-window' => 'An ordered-set aggregate cannot be used as a window function.',
            'default-context' => 'DEFAULT requires a column write or a setting assignment.',
            'foreign-key-column-count' => 'A foreign key requires matching local and referenced column counts.',
            'invalid-concurrent-index-drop' => 'Concurrent index deletion requires one index and cannot cascade.',
            'json-option' => 'The JSON option is outside the domain accepted by this operation.',
            'xml-option' => 'The XML column option is unknown or specified more than once.',
            'cursor-options' => 'A cursor cannot request both SCROLL and NO SCROLL.',
            'scalar-query-width' => 'A scalar subquery requires exactly one result column.',
            'comparison-width' => 'Compared row operands must have equal widths.',
            'joined-mutation-pagination' => 'A MySQL joined mutation cannot specify ORDER BY or LIMIT.',
            'qualified-trigger-target' => 'A SQLite trigger mutation requires an unqualified target name.',
            'trigger-index-hint' => 'A SQLite trigger mutation cannot specify an index hint.',
            'added-column-key' => 'SQLite ADD COLUMN cannot declare a PRIMARY KEY or UNIQUE constraint.',
            'multiple-primary-keys' => 'A table can declare only one primary key.',
            'invalid-output-position' => 'ORDER BY position is outside the result.',
            'invalid-explain-option' => 'The EXPLAIN option is not defined by this database language.',
            'invalid-explain-setting' => 'The EXPLAIN option requires a value in its declared domain.',
            'invalid-explain-combination' => 'The EXPLAIN options have incompatible execution requirements.',
            'assignment-column-count' => 'Assignment destinations and values must have the same width.',
            'tuple-source' => 'A tuple assignment requires a row constructor or a row-producing query.',
            'insert-column-count' => 'INSERT destinations and input values must have the same width.',
            'values-column-count' => 'VALUES rows must have the same width.',
            'set-column-count' => 'Set-operation operands must have the same result width.',
            'invalid-alter-algorithm' => 'The requested ALTER algorithm is not defined by this database language.',
            'invalid-alter-lock' => 'The requested ALTER lock mode is not defined by this database language.',
            'cte-column-count' => 'CTE aliases must match the declared query result positions.',
            'duplicate-cte' => 'A WITH clause cannot define the same relation name twice.',
        ][$this->value];
    }
}
