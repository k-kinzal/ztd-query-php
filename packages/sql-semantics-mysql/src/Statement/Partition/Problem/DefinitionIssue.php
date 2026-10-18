<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * An intrinsic incompatibility between partition definitions and their method.
 *
 * @visibility public
 * @example Describing inconsistent subpartition counts
 *     \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount->message() // => 'Wrong number of subpartitions defined, mismatch with previous setting'
 */
enum DefinitionIssue implements Diagnostic
{
    case SubpartitionCount;
    case ZeroSubpartitions;
    case MissingRangeValues;
    case MissingListValues;
    case RangeValues;
    case ListValues;

    /**
     * Describes the incompatible definition without retaining its input spelling.
     */
    public function message(): string
    {
        return match ($this) {
            self::ZeroSubpartitions => 'Number of subpartitions = 0 is not an allowed value',
            self::SubpartitionCount => 'Wrong number of subpartitions defined, mismatch with previous setting',
            self::MissingRangeValues => 'Syntax error: RANGE PARTITIONING requires definition of VALUES LESS THAN for each partition',
            self::MissingListValues => 'Syntax error: LIST PARTITIONING requires definition of VALUES IN for each partition',
            self::RangeValues => 'Only RANGE PARTITIONING can use VALUES LESS THAN in partition definition',
            self::ListValues => 'Only LIST PARTITIONING can use VALUES IN in partition definition',
        };
    }
}
