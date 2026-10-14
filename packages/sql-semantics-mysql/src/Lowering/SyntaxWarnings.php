<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxCasts;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxNames;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Interprets notices of completed expressions before a later input refusal.
 *
 * BINARY and assignment expressions warn after their operand has been read.
 * A missing operand therefore produces no notice. The temporary parser nodes
 * are consumed during the call; only warning descriptors and offsets escape.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 *
 * @visibility public
 * @example Preserving a completed BINARY warning before an unprepared marker
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
 *     $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, 'SELECT BINARY 1,?', 16, $profile);
 *     array_column($notices, 1) // => [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator]
 */
final class SyntaxWarnings
{
    /**
     * Returns completion notices raised before the token at the refusal offset.
     *
     * @return list<array{int, Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|ParseFailure}> Input boundaries and semantic notices
     */
    public function completed(ReductionParser $parser, string $statement, int $end, LanguageProfile $profile): array
    {
        $warnings = [];
        $ranges = new SourceRanges();
        $productions = Productions::load(dirname(__DIR__, 2) . '/resources/productions/' . $profile->grammar->value . '.php');
        $lowering = new Lowering($productions, new Leaves(), $profile);
        $aborted = false;
        try {
            $parser->parse($statement, static function (Node $node, Token $lookahead) use (&$warnings, &$aborted, $ranges, $end, $profile, $lowering, $parser, $statement): void {
                if ($aborted || $lookahead->offset > $end) {
                    return;
                }
                $replication = new Replication\SyntaxDeprecations();
                $notice = (new SyntaxDeprecations())->completed($node, $profile->grammar) ?? $replication->completed($node, $lowering, $replication->preceding($node, $parser, $statement)) ?? (new Replication\SyntaxSettings())->completed($node, $lowering) ?? (new SyntaxNames())->completed($node, $lowering) ?? (new SyntaxCasts())->completed($node, $lowering);
                if ($notice !== null) {
                    $range = $ranges->span($node, true);
                    if ($range !== null) {
                        $warnings[] = [$range[1], $notice];
                        $aborted = $notice instanceof ParseFailure && $notice->aborts;
                    }
                }
            });
        } catch (SourceException) {
            // Completed reductions precede the original refusal; the caller owns that error.
        }

        return $warnings;
    }
}
