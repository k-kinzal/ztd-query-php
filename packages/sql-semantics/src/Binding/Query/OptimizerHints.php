<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Query\Optimization;

/**
 * Classifies directives in the comment immediately following this SELECT keyword.
 * @visibility SqlSemantics
 */
final class OptimizerHints
{
    /**
     * @return list<Optimization\OptimizerHint>
     * @throws UnclassifiedSql
     */
    public static function bind(Node $source): array
    {
        $tokens = $source->tokens();
        if (strtoupper($tokens[0]->text ?? '') !== 'SELECT' || !isset($tokens[1])) {
            return [];
        }
        preg_match_all('/\/\*\+(.*?)\*\//s', $tokens[1]->leading, $comments);
        $result = [];
        foreach ($comments[1] as $comment) {
            $remaining = trim($comment);
            while ($remaining !== '') {
                if (preg_match('/\AMAX_EXECUTION_TIME\s*\(\s*([0-9]+)\s*\)\s*/i', $remaining, $match) !== 1) {
                    throw new UnclassifiedSql('Unclassified optimizer hint: ' . $remaining);
                }
                $result[] = new Optimization\MaxExecutionTime($match[1]);
                $remaining = substr($remaining, strlen($match[0]));
            }
        }
        return $result;
    }
}
