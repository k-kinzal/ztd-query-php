<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxCasts;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxNames;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Dispatches completed grammar productions to their family's notice rule.
 *
 * The ordering and stopping boundary belong to SyntaxWarnings; each family
 * retains the same operand interpretation as ordinary semantic lowering.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CompletedNotice
{
    /**
     * Interprets one completed production before the caller orders its notices.
     */
    public function of(Node $node, Lowering $lowering, ReductionParser $parser, string $statement, ?\SqlParser\Lexer\Token $lookahead = null): Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|ParseFailure|null
    {
        $entry = (new ClientEntry())->generated($node) ?? (new Replication\SyntaxLogMoment())->rejected($node, $lowering->profile->grammar);
        if ($entry !== null) {
            return new ParseFailure($entry->problem, true);
        }
        $replication = new Replication\SyntaxDeprecations();
        return $this->operands($node, $lowering) ?? $lowering->tableChanges->completed($node) ?? $lowering->numbers->completed($node) ?? (new Server\Transaction\SyntaxXids())->completed($node, $lowering) ?? (new Account\SyntaxRoles())->deprecation($node, $lowering->profile->grammar) ?? (new Account\SyntaxPrivileges())->completed($node, $lowering) ?? (new Account\SyntaxFactors())->completed($node, $lowering) ?? (new SyntaxDeprecations())->completed($node, $lowering->profile->grammar) ?? (new Dml\Insert\SyntaxDeprecations())->completed($node, $lowering->profile->grammar) ?? $replication->completed($node, $lowering, $replication->preceding($node, $parser, $statement, $lookahead)) ?? (new Replication\SyntaxSettings())->completed($node, $lowering) ?? (new Utility\Explain\SyntaxFormats())->completed($node, $lowering) ?? (new Query\SyntaxCommonTables())->completed($node, $lowering) ?? (new Server\Instance\SyntaxOptions())->completed($node, $lowering) ?? (new Routine\Condition\SyntaxConditions())->completed($node, $lowering) ?? (new Routine\SyntaxParameters())->completed($node, $lowering) ?? (new Routine\Program\SyntaxDeclarations())->completed($node, $lowering) ?? (new Routine\Program\SyntaxStatements())->completed($node, $lowering);
    }

    /**
     * Interprets primitive name and cast operands before their enclosing constructs.
     */
    public function operands(Node $node, Lowering $lowering): Deprecated|ParseFailure|null
    {
        return (new Query\SyntaxAliases())->completed($node, $lowering) ?? (new Leaf\SyntaxTableNames())->completed($node, $lowering) ?? (new SyntaxNames())->completed($node, $lowering) ?? (new SyntaxCasts())->completed($node, $lowering) ?? (new Routine\DeclarationPrefix())->completed($node, $lowering);
    }

}
