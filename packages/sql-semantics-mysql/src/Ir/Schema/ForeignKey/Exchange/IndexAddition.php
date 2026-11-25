<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An explicit secondary key addition in a native table key alteration.
 * @visibility public
 * @example Inspecting a resolved secondary key on the owning table
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.p (id INT PRIMARY KEY)', 'CREATE TABLE app.c (id INT PRIMARY KEY, pid INT)']);
 *     $semantics->analyze('ALTER TABLE app.c ADD KEY route (pid), ADD FOREIGN KEY (pid) REFERENCES p(id)', $catalog)->actions[0]->toString() // => 'ADD INDEX `route` (`pid`)'
 */
final class IndexAddition
{
    use Snapshot;

    /**
     * The resolved named whole key, including its possible clustered placement.
     */
    public readonly ForeignKeyNativeKey $key;

    /**
     * Checks the exact table coordinates and the signed whole-field operands of this secondary key.
     */
    public function __construct(
        ForeignKeyNativeKey $key,
        TableSource $heading,
        public readonly bool $automatic = false,
    ) {
        $identity = $key->identity;
        Check::input($identity->owner->kind === $heading->kind && $identity->owner->name->schema->value === $heading->schema->value
            && $identity->owner->name->table->value === $heading->table->value, 'An added index needs the exact declared owning table.');
        Check::input(
            $key->full && $key->referenceKey && !$key->implicitForeignKey
            && ($identity->role === IndexRole::Ordinary || $identity->role === IndexRole::Unique),
            'An added index needs a full explicit ordinary or unique secondary key.'
        );
        $declared = [];
        foreach ($heading->columns as $slot => $column) {
            $declared[$column->value] = $heading->columnTypes[$slot];
        }
        $required = $identity->role === IndexRole::Unique;
        foreach ($key->fields as $field) {
            $type = $declared[$field->field->value] ?? null;
            Check::input(
                $type === ColumnKind::SignedInt || $type === ColumnKind::RequiredSignedInt,
                'An added index requires exact whole signed fields of the declared owning table.'
            );
            $required = $required && $type === ColumnKind::RequiredSignedInt;
        }
        $this->key = new ForeignKeyNativeKey($identity, $key->fields, true, clusterEligible: $required);
    }

    /**
     * Emits one typed index clause in the native allocation form.
     */
    public function toString(): string
    {
        $fields = [];
        foreach ($this->key->fields as $field) {
            $fields[] = $field->field->quoted() . ($field->descending ? ' DESC' : '');
        }
        $role = $this->key->identity->role === IndexRole::Unique ? 'UNIQUE ' : '';
        $name = $this->automatic ? '' : $this->key->identity->name->quoted() . ' ';

        return 'ADD ' . $role . 'INDEX ' . $name . '(' . implode(', ', $fields) . ')';
    }
}
