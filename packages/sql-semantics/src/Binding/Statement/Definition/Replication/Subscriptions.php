<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Replication;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Replication\Subscription\PublicationListChange;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionParameter as P;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE, ALTER, and DROP SUBSCRIPTION with the options each form accepts.
 * @visibility SqlSemantics
 */
final class Subscriptions
{
    /**
     * Separates each subscription form; a rejected option combination is diagnosed.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $name = $context->tables->identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A subscription requires its name.'))->tokens()[0]);
        try {
            if ($source->name === 'DropSubscriptionStmt') {
                $behavior = Tree::child($source, ['opt_drop_behavior']);
                return new Statement\DropSubscriptionStatement($origin, $name, strtoupper($source->tokens()[2]->text) === 'IF', $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
            }
            if ($source->name === 'CreateSubscriptionStmt') {
                return new Statement\CreateSubscriptionStatement($origin, $name, self::connection($source, $context), self::publications($source, $context), SubscriptionOptionReader::read(Tree::outer($source, ['definition'])[0] ?? null, [P::Connect, P::Enabled, P::CreateSlot, P::SlotName, P::CopyData, P::SynchronousCommit, P::Binary, P::Streaming, P::TwoPhase, P::DisableOnError, P::PasswordRequired, P::RunAsOwner, P::Failover, P::Origin], $context));
            }
            return self::alter($origin, $source, $name, $context);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * Separates the ALTER SUBSCRIPTION forms by their leading keywords.
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function alter(Origin $origin, Node $source, string $name, QueryContext $context): BoundStatement
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), array_slice($source->tokens(), 3, 2));
        $definition = Tree::outer($source, ['definition'])[0] ?? null;
        return match (true) {
            $words[0] === 'CONNECTION' => new Statement\AlterSubscriptionConnectionStatement($origin, $name, self::connection($source, $context)),
            $words[0] === 'ENABLE', $words[0] === 'DISABLE' => new Statement\AlterSubscriptionEnabledStatement($origin, $name, $words[0] === 'ENABLE'),
            $words[0] === 'REFRESH' => new Statement\RefreshSubscriptionStatement($origin, $name, SubscriptionOptionReader::read($definition, [P::CopyData], $context)),
            $words[0] === 'SKIP' => new Statement\SkipSubscriptionTransactionStatement($origin, $name, SubscriptionOptionReader::lsn($definition ?? throw new UnclassifiedSql('SKIP requires its options.'), $context)),
            ($words[1] ?? '') === 'PUBLICATION' => new Statement\AlterSubscriptionPublicationsStatement($origin, $name, PublicationListChange::from($words[0]), self::publications($source, $context), SubscriptionOptionReader::read($definition, [P::Refresh, P::CopyData], $context)),
            default => new Statement\AlterSubscriptionOptionsStatement($origin, $name, SubscriptionOptionReader::read($definition, [P::SlotName, P::SynchronousCommit, P::Binary, P::Streaming, P::DisableOnError, P::PasswordRequired, P::RunAsOwner, P::Failover, P::Origin], $context)),
        };
    }

    /**
     * The decoded connection string, kept without parsing it.
     * @throws UnclassifiedSql
     */
    public static function connection(Node $source, QueryContext $context): string
    {
        $constant = Tree::child($source, ['Sconst']) ?? throw new UnclassifiedSql('A subscription connection requires its string.');
        return FieldSpelling::read($constant->tokens()[0], $context->tables->identifiers);
    }

    /**
     * The publication names in written order.
     * @return list<string>
     * @throws UnclassifiedSql
     */
    public static function publications(Node $source, QueryContext $context): array
    {
        $list = Tree::child($source, ['name_list']) ?? throw new UnclassifiedSql('A subscription requires its publications.');
        return array_map(static fn (Node $name): string => $context->tables->identifiers->name($name->tokens()[0]), Tree::outer($list, ['name']));
    }
}
