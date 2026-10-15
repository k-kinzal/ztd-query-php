<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;

/**
 * Rejects grammar entries that cannot denote a client statement.
 *
 * MySQL 5.6 and 5.7 refuse a leading PARTITION before inspecting its body.
 * The refusal retains only its typed reason and the keyword's end offset.
 * MySQL 5.7 refuses PARSE_GCOL_EXPR after its operands complete, retaining
 * the keyword offset separately from that completion boundary.
 * Observed on native 5.6.51 and 5.7.44 servers.
 *
 * @visibility public
 * @example Refusing a standalone partitioning entry
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-5.7.44');
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5744);
 *     (new \SqlSemantics\Platform\MySql\Lowering\ClientEntry())->unparsed($parser, 'PARTITION BROKEN', $profile)?->end // => 9
 */
final class ClientEntry
{
    /**
     * Refuses a completed internal generated-column entry after its operands were read.
     */
    public function generated(Node $node): ?RejectedInput
    {
        if ($node->name !== 'parse_gcol_expr') {
            return null;
        }
        $tokens = $node->tokens();

        return new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry($tokens[0]->offset), $tokens[count($tokens) - 1]->end());
    }

    /**
     * Answers the refusal attached to the first token, independently of later operands.
     */
    public function partition(?Token $first, LanguageProfile $profile): ?RejectedInput
    {
        return $first?->name === 'PARTITION_SYM' && in_array($profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)
            ? new RejectedInput(new PartitionEntryRefused(), $first->end())
            : null;
    }

    /**
     * Inspects a tokenizable prefix when the complete input could not be parsed.
     */
    public function unparsed(SqlParser $parser, string $sql, LanguageProfile $profile): ?RejectedInput
    {
        if (!in_array($profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        try {
            $tokens = $parser->tokenize($sql);
        } catch (SourceException $error) {
            $tokens = $parser->tokenize(substr($sql, 0, $error->offset));
        }

        return $this->partition($tokens[0] ?? null, $profile);
    }

    /**
     * Identifies a legacy client entry inside an unfinished stored program.
     *
     * A fixed valid partition body lets the parser identify the keyword's
     * production despite a malformed original body. Only partition_entry is
     * refused; the same keyword in a table clause keeps its ordinary meaning.
     * The probe is never lowered, retained or executed.
     */
    public function nested(SqlParser $parser, string $sql, LanguageProfile $profile): ?RejectedInput
    {
        if (!$parser instanceof ReductionParser || !in_array($profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        try {
            $tokens = $parser->tokenize($sql);
        } catch (SourceException $error) {
            $tokens = $parser->tokenize(substr($sql, 0, $error->offset));
        }
        foreach ($tokens as $token) {
            if ($token->name !== 'PARTITION_SYM') {
                continue;
            }
            $entry = false;
            try {
                $parser->parse(substr($sql, 0, $token->end()) . ' BY HASH(1);', static function (Node $node) use ($token, &$entry): void {
                    $entry = $entry || ($node->name === 'partition_entry' && ($node->tokens()[0]->offset ?? -1) === $token->offset);
                });
            } catch (SourceException) {
                // A surrounding program may still be unfinished after the entry completes.
            }
            if ($entry) {
                return $this->partition($token, $profile);
            }
        }

        return null;
    }
}
