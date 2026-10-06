<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\AlterSubscriptionConnection;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\AlterSubscriptionOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\AlterSubscriptionPublications;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\CreateSubscription;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\DropSubscription;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\EnableSubscription;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\RefreshSubscription;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\SubscriptionOptionAction;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the subscription commands.
 *
 * Rule: PG-SUBSCRIPTION-LOWER-001. Scope: `CreateSubscriptionStmt`,
 * `AlterSubscriptionStmt`, `DropSubscriptionStmt`. Constructors: the classes
 * of `Statement\Publication\Subscription`.
 * Source: https://www.postgresql.org/docs/17/sql-createsubscription.html, https://www.postgresql.org/docs/17/sql-altersubscription.html,
 * https://www.postgresql.org/docs/17/sql-dropsubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class SubscriptionRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a subscription command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $options = $this->lowering->options;
        $flags = $this->lowering->flags;

        return match ($form->signature) {
            'CreateSubscriptionStmt: CREATE SUBSCRIPTION name CONNECTION Sconst PUBLICATION name_list opt_definition' => new CreateSubscription($names->name($form->node(2)), $this->lowering->literals->string($form->node(4)), $names->names($form->node(6)), $options->definitions($form->node(7))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name SET definition' => new AlterSubscriptionOptions($names->name($form->node(2)), SubscriptionOptionAction::Set, $options->definitions($form->node(4))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name SKIP definition' => new AlterSubscriptionOptions($names->name($form->node(2)), SubscriptionOptionAction::Skip, $options->definitions($form->node(4))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name CONNECTION Sconst' => new AlterSubscriptionConnection($names->name($form->node(2)), $this->lowering->literals->string($form->node(4))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name REFRESH PUBLICATION opt_definition' => new RefreshSubscription($names->name($form->node(2)), $options->definitions($form->node(5))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name ADD_P PUBLICATION name_list opt_definition' => new AlterSubscriptionPublications($names->name($form->node(2)), PublicationAction::Add, $names->names($form->node(5)), $options->definitions($form->node(6))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name DROP PUBLICATION name_list opt_definition' => new AlterSubscriptionPublications($names->name($form->node(2)), PublicationAction::Drop, $names->names($form->node(5)), $options->definitions($form->node(6))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name SET PUBLICATION name_list opt_definition' => new AlterSubscriptionPublications($names->name($form->node(2)), PublicationAction::Set, $names->names($form->node(5)), $options->definitions($form->node(6))),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name ENABLE_P' => new EnableSubscription($names->name($form->node(2)), true),
            'AlterSubscriptionStmt: ALTER SUBSCRIPTION name DISABLE_P' => new EnableSubscription($names->name($form->node(2)), false),
            'DropSubscriptionStmt: DROP SUBSCRIPTION name opt_drop_behavior' => new DropSubscription($names->name($form->node(2)), false, $flags->dropBehavior($form->node(3))),
            'DropSubscriptionStmt: DROP SUBSCRIPTION IF_P EXISTS name opt_drop_behavior' => new DropSubscription($names->name($form->node(4)), true, $flags->dropBehavior($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }
}
