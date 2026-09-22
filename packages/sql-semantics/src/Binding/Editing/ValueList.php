<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Editing;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Configuration\Setting;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Transformation\SourceEdit;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Locates a complete setting value list, including its grammar separators.
 * @visibility SqlSemantics
 */
final class ValueList
{
    /**
     * @param list<Expression> $values
     * @throws InvalidStructure
     */
    public static function replace(ConfigurationStatement $statement, Setting $setting, array $values): Sql\Tree
    {
        $first = $setting->values[0]->source;
        $last = $setting->values[count($setting->values) - 1]->source;
        $head = $first instanceof Token ? [$first] : $first->tokens();
        $tail = $last instanceof Token ? [$last] : $last->tokens();
        $tokens = $statement->source->tokens();
        $start = array_search($head[0] ?? null, $tokens, true);
        $end = array_search($tail[count($tail) - 1] ?? null, $tokens, true);
        if ($start === false || $end === false || $end < $start) {
            throw new InvalidStructure('The setting values do not belong to this statement.');
        }
        $source = new Node('values', 0, array_slice($tokens, $start, $end - $start + 1));
        return SourceEdit::replace($statement->source, $statement->sql, $source, Sql\Build::separated(array_map(static fn (Expression $value): Sql\Tree => $value->sql, $values)));
    }
}
