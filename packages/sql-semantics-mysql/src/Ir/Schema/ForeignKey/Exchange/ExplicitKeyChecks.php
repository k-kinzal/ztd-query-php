<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyIndexPrefix;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Closes explicit secondary keys against one native table alteration.
 * @visibility SqlSemantics
 * @phpstan-import-type ExplicitIndexRequest from \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyChildIndexes
 */
final class ExplicitKeyChecks
{
    /**
     * Checks contextual ownership and release requirements, then returns native name requests.
     * @param list<ForeignKeyNativeKey> $original Plain physical keys in the starting schema
     * @param array<int, IndexAddition> $indexes Checked explicit additions by action position
     * @return array<int, ExplicitIndexRequest>
     */
    public function requests(TableIdentity $owner, array $original, array $indexes, bool $legacy): array
    {
        if ($indexes === []) {
            return [];
        }
        $clustered = false;
        foreach ((new ForeignKeyIndexPrefix())->keys($owner, $original) as $key) {
            $clustered = $clustered || $key->identity->role->clustered();
        }
        Check::input($clustered, 'A mixed secondary index addition requires an original clustered identity.');
        $requests = [];
        foreach ($indexes as $position => $index) {
            $identity = $index->key->identity;
            Check::input(
                $identity->owner->kind === $owner->kind && $identity->owner->quoted() === $owner->quoted(),
                'The added physical index must belong to the altered table.'
            );
            foreach ($index->key->fields as $field) {
                Check::input(!$legacy || !$field->descending, 'This MySQL release does not store descending index parts.');
            }
            $fields = $index->key->fields;
            Check::invariant($fields !== [], 'A checked explicit secondary key has whole fields.');
            $requests[$position] = [$index->automatic ? null : $identity->name, $fields];
        }

        return $requests;
    }
}
