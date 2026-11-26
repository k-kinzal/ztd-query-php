<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One resolved native foreign-key declaration relative to its owning table.
 * @visibility public
 * @example Printing one resolved foreign-key addition
 *     $root = 'SqlSemantics\\Platform\\MySql\\Ir\\';
 *     $name = $root . 'Identifier';
 *     $table = $root . 'Schema\\TableName';
 *     $identity = $root . 'Schema\\Identity\\TableIdentity';
 *     $parent = new $identity(new $table(new $name('app'), new $name('parent')), \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     $domain = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain(\SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Standard, false);
 *     $pairs = [new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair(new $name('x'), new $name('id'), $domain, true)];
 *     $effects = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffects(\SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction::Cascade, \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction::NoAction);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition($parent, new $name('owner_ref'), $pairs, $effects))->toString() // => 'ADD CONSTRAINT `owner_ref` FOREIGN KEY (`x`) REFERENCES `app`.`parent` (`id`) ON UPDATE CASCADE ON DELETE NO ACTION'
 */
final class ForeignKeyAddition
{
    use Snapshot;

    /**
     * @var non-empty-list<ForeignKeyPair> Native pairs in declared child and reference order
     */
    public readonly array $pairs;

    /**
     * Checks the native declaration roles before assembling the enclosing statement.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $pairs The ordered resolved reference pairs
     */
    public function __construct(
        public readonly TableIdentity $parent,
        public readonly Identifier $symbol,
        array $pairs,
        public readonly ForeignKeyEffects $effects,
        public readonly bool $generated = false,
        public readonly ?Identifier $indexName = null,
        public readonly ?Identifier $createdIndex = null,
    ) {
        Check::input($parent->kind === ExternalRelationKind::BaseTable, 'A MySQL foreign key must refer to a persistent InnoDB table.');
        Check::input(preg_match('/\A.{0,64}\z/us', $symbol->value) === 1, 'A native foreign-key name has at most 64 characters.');
        $selected = Check::listOf($pairs, ForeignKeyPair::class, 'A native foreign key requires checked field pairs.', 1);
        Check::input(count($selected) <= 16, 'A native foreign key requires one through sixteen ordered field pairs.');
        Check::input(
            $indexName === null || ($generated && $createdIndex !== null && $indexName->value === $createdIndex->value),
            'Only a generated constraint creating its named physical key consumes an index label.'
        );
        $identifiers = new NativeIdentifierCase();
        $children = $parents = [];
        foreach ($selected as $pair) {
            $child = $identifiers->fold($pair->child->value);
            $referenced = $identifiers->fold($pair->parent->value);
            Check::input(!isset($children[$child]) && !isset($parents[$referenced]), 'A native foreign key cannot repeat a field on either side.');
            Check::input(
                $pair->nullable || ($effects->update !== ForeignKeyAction::SetNull && $effects->delete !== ForeignKeyAction::SetNull),
                'SET NULL requires nullable native child fields.'
            );
            $children[$child] = $parents[$referenced] = true;
        }
        $this->pairs = $selected;
    }

    /**
     * Emits the declared name policy, native paired columns and referential actions.
     */
    public function toString(): string
    {
        $symbol = $this->generated ? '' : 'CONSTRAINT ' . $this->symbol->quoted() . ' ';
        $index = $this->indexName === null ? '' : $this->indexName->quoted() . ' ';
        $children = $parents = [];
        foreach ($this->pairs as $pair) {
            $children[] = $pair->child->quoted() . ($pair->descending ? ' DESC' : '');
            $parents[] = $pair->parent->quoted();
        }

        return 'ADD ' . $symbol . 'FOREIGN KEY ' . $index . '(' . implode(', ', $children) . ') REFERENCES '
            . $this->parent->quoted() . ' (' . implode(', ', $parents) . ')' . $this->effects->sql();
    }
}
