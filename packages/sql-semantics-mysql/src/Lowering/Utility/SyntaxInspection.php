<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\SourceRanges;

/**
 * Locates a completed debug inspection before a later input error.
 *
 * A release build refuses SHOW PARSE_TREE when its inspected statement reduces,
 * including when an unrelated trailing token remains. An unfinished statement
 * never reaches this check. Only two byte offsets escape the temporary parse.
 * Observed through text and binary preparation on MySQL 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxInspection
{
    /**
     * @return array{int, int}|null Refused keyword offset and completed statement boundary
     */
    public function completed(ReductionParser $parser, string $statement, int $end): ?array
    {
        if (stripos($statement, 'PARSE_TREE') === false) {
            return null;
        }
        $result = null;
        try {
            $parser->parse($statement, static function (Node $node) use (&$result, $end): void {
                if ($result !== null || $node->name !== 'show_parse_tree_stmt') {
                    return;
                }
                $boundary = (new SourceRanges())->span($node)[1] ?? PHP_INT_MAX;
                if ($boundary <= $end) {
                    $result = [$node->tokens()[1]->offset, $boundary];
                }
            });
        } catch (SourceException) {
            // The caller owns the later grammar refusal; completed input stays observable.
        }

        return $result;
    }
}
