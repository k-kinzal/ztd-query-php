<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Two tables, one of which takes its children with it.
 *
 * The smallest shape in which a statement's consequences can be seen reaching
 * past the table it was written against, which is what several tests are
 * about and none of them is about setting up.
 */
final class CascadingShop
{
    /**
     * Answers the two tables and the key between them.
     *
     * @return TableDefinitionRegistry What describes them
     */
    public static function registry(): TableDefinitionRegistry
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], [], ['id'], [], []));
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id'], ReferentialAction::Cascade),
        ]));

        return $registry;
    }

    /**
     * Answers a shadow holding one parent and the one child referencing it.
     *
     * @return ShadowStore The shadow
     */
    public static function shadow(): ShadowStore
    {
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);

        return $store;
    }
}
