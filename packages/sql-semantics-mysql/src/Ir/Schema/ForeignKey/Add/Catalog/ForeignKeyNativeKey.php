<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The leading full key fields available to check one native foreign-key statement.
 * @visibility public
 * @example Describing a single native primary column
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new $name('app'), new $name('parent'));
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($table, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     $index = new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity($owner, new $name('PRIMARY'), \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::Primary);
 *     $field = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn(new $name('id'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey($index, [$field], true))->fields[0]->field->value // => 'id'
 */
final class ForeignKeyNativeKey
{
    use Snapshot;

    /**
     * @var list<ForeignKeyKeyColumn> Consecutive resolved fields before an expression or prefix field
     */
    public readonly array $fields;

    /**
     * Whether this whole unique key can become the native clustered identity.
     */
    public readonly bool $clusterEligible;

    /**
     * Fixes the native key name, role, prefix fields and completeness of the prefix.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $fields The leading full fields
     */
    public function __construct(
        public readonly IndexIdentity $identity,
        array $fields,
        public readonly bool $full,
        public readonly bool $referenceKey = true,
        public readonly bool $implicitForeignKey = false,
        bool $clusterEligible = false,
    ) {
        Check::input($identity->owner->kind === ExternalRelationKind::BaseTable, 'A native foreign-key key fact must belong to a persistent table.');
        $checked = Check::listOf($fields, ForeignKeyKeyColumn::class, 'A native key fact requires checked prefix fields.');
        Check::input(count($checked) <= 16 && (!$full || $checked !== []), 'A native key fact requires a valid full-field prefix.');
        Check::input($referenceKey || (!$full && $fields === []), 'A non-B-tree key supplies no native reference prefix.');
        Check::input(
            !$implicitForeignKey || ($referenceKey && $full && $identity->role === IndexRole::Ordinary),
            'An implicit foreign-key index requires a full ordinary native key.',
        );
        $eligible = $clusterEligible || $identity->role === IndexRole::ClusteredUnique;
        Check::input(
            !$eligible || ($referenceKey && $full && ($identity->role === IndexRole::Unique || $identity->role === IndexRole::ClusteredUnique)),
            'A clustered candidate must be a whole native unique key.'
        );
        $case = new NativeIdentifierCase();
        $seen = [];
        foreach ($checked as $field) {
            $name = $case->fold($field->field->value);
            Check::input(!isset($seen[$name]), 'A native key fact cannot repeat a prefix field.');
            $seen[$name] = true;
        }
        $this->fields = $checked;
        $this->clusterEligible = $eligible;
    }
}
