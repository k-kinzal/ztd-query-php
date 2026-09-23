<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Transaction\Xa as Statement;
use SqlSemantics\Model\Transaction\Xa;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the distinct operations of the MySQL XA transaction grammar.
 * @visibility SqlSemantics
 */
final class XaBinder
{
    /**
     * Classifies the operation before interpreting its required identifier and policy.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || $node->name !== 'xa') {
            return null;
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $node->tokens());
        $operation = $words[1] ?? '';
        if ($operation === 'RECOVER') {
            return new Statement\XaRecoverStatement($origin, in_array('CONVERT', $words, true) ? Xa\RecoveryEncoding::Hexadecimal : Xa\RecoveryEncoding::Bytes);
        }
        $identifier = Tree::outer($node, ['xid'])[0] ?? null;
        if ($identifier === null) {
            throw new UnclassifiedSql('An XA operation requires its transaction identifier: ' . $node->toString());
        }
        $id = self::identifier($identifier);
        $tail = strtoupper(Tree::text($node->children[count($node->children) - 1]));
        return match ($operation) {
            'START', 'BEGIN' => new Statement\XaStartStatement($origin, $id, Xa\StartMode::from($tail)),
            'END' => new Statement\XaEndStatement($origin, $id, Xa\EndMode::from($tail)),
            'PREPARE' => new Statement\XaPrepareStatement($origin, $id),
            'COMMIT' => new Statement\XaCommitStatement($origin, $id, Xa\CommitMode::from($tail)),
            'ROLLBACK' => new Statement\XaRollbackStatement($origin, $id),
            default => throw new UnclassifiedSql('Unclassified XA operation: ' . $node->toString()),
        };
    }

    /**
     * Preserves literal spellings and classifies an explicit branch and format separately.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function identifier(Node $node): Xa\TransactionId
    {
        $literals = [];
        $binder = new LiteralBinder(Dialect::MySql);
        foreach (Tree::outer($node, ['text_string']) as $part) {
            $literal = $binder->bind($part->tokens()[0]);
            if (!$literal instanceof Literal) {
                throw new UnclassifiedSql('Unclassified XA identifier literal: ' . $part->toString());
            }
            $literals[] = $literal;
        }
        if (!isset($literals[0])) {
            throw new UnclassifiedSql('An XA identifier requires a global identifier literal.');
        }
        $format = Tree::outer($node, ['ulong_num'])[0] ?? null;
        try {
            $branch = isset($literals[1]) ? new Xa\BranchIdentifier($literals[1], $format === null ? null : new Xa\FormatIdentifier(Tree::text($format))) : null;
            return new Xa\TransactionId($literals[0], $branch);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::XaIdentifier, $node, $error);
        }
    }
}
