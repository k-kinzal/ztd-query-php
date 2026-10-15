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
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseStop;

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
     * @return list<array{int, Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|ParseFailure|ParseStop}> Input boundaries and semantic notices
     */
    public function completed(ReductionParser $parser, string $statement, int $end, LanguageProfile $profile): array
    {
        $tokens = (new InputTokens())->prefix($parser, $statement);
        $statement = substr($statement, 0, $tokens[count($tokens) - 1]->offset);
        $warnings = (new Account\SyntaxResourceNames())->completed($parser, $statement, $end);
        $ranges = new SourceRanges();
        $productions = Productions::load(dirname(__DIR__, 2) . '/resources/productions/' . $profile->grammar->value . '.php');
        $lowering = new Lowering($productions, new Leaves(), $profile);
        $aborted = false;
        $failed = false;
        $names = new Utility\Set\SyntaxNames();
        try {
            $parser->parse($statement, static function (Node $node, Token $lookahead) use (&$warnings, &$aborted, &$failed, $ranges, $end, $lowering, $parser, $statement, $names): void {
                if ($aborted || $lookahead->offset > $end) {
                    return;
                }
                $log = (new Replication\SyntaxLogMoment())->starting($node, $lookahead, $parser, $statement, $lowering->profile->grammar);
                if ($log !== null && $log->end <= $end) {
                    $warnings[] = [$log->end, new ParseFailure($log->problem, true)];
                    $aborted = true;
                    return;
                }
                if ($failed && in_array($node->name, ['sp_proc_stmt', 'ev_sql_stmt_inner'], true)) {
                    $warnings[] = [($ranges->span($node, true)[1] ?? 0), ParseStop::ProgramStatement];
                    $aborted = true;
                    return;
                }
                $notice = ($failed ? null : $names->completed($node, $lowering)) ?? (new CompletedNotice())->of($node, $lowering, $parser, $statement, $lookahead);
                $conditions = $notice === null ? [...(new Type\SyntaxNotices())->completed($node, $lowering), ...(new Account\SyntaxRoles())->completed($node, $lowering)] : [$notice];
                foreach ($conditions as $condition) {
                    $range = $ranges->span($node, true) ?? [$lookahead->offset, $lookahead->offset];
                    $warnings[] = [$range[1], $condition];
                    $failed = $failed || ($condition instanceof ParseFailure && $condition->endsProgramStatement);
                    $aborted = ($condition instanceof ParseFailure || $condition instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation) && $condition->aborts;
                }
            });
        } catch (SourceException) {
            // Completed reductions precede the original refusal; the caller owns that error.
        }

        return $this->ordered($warnings);
    }

    /**
     * Orders completed notices and retains only those through the first stopping condition.
     *
     * @param list<array{int, Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|ParseFailure|ParseStop}> $warnings Completed input notices
     * @return list<array{int, Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|ParseFailure|ParseStop}> Ordered notices up to the stopping boundary
     */
    public function ordered(array $warnings): array
    {
        usort($warnings, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        foreach ($warnings as $index => [, $notice]) {
            if ($notice instanceof ParseStop || (($notice instanceof ParseFailure || $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation) && $notice->aborts)) {
                return array_slice($warnings, 0, $index + 1);
            }
        }

        return $warnings;
    }

    /**
     * Derives completed program operands before a refused value, preserving lexical scopes.
     */
    public function before(Node $tree, int $end, LanguageProfile $profile): ?\SqlSemantics\Construction\RejectedInput
    {
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 2) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile);

        return (new Routine\Program\InputPrefix($lowering))->before($tree, $end);
    }

    /**
     * Finds a rejected operand of a parsed input, retaining earlier program-scope refusals.
     */
    public function rejected(Node $tree, LanguageProfile $profile): ?\SqlSemantics\Construction\RejectedInput
    {
        $entry = (new ClientEntry())->partition($tree->tokens()[0] ?? null, $profile);
        if ($entry !== null) {
            return $entry;
        }
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 2) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile);
        $pending = [$tree];
        while ($pending !== []) {
            $condition = array_pop($pending);
            $failure = (new RejectedOperand())->of($condition, $lowering);
            if ($failure !== null) {
                if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment) {
                    $failure = new \SqlSemantics\Construction\RejectedInput((new Utility\Set\SyntaxNames())->scoped($tree, $failure->problem, $lowering), $failure->end, $failure->aborts);
                }
                return (new Routine\Program\InputPrefix($lowering))->before($tree, $failure->end) ?? $failure;
            }
            foreach (array_reverse($condition->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return null;
    }
}
