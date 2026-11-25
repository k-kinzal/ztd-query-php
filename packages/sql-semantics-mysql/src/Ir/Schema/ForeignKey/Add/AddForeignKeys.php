<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Ordered native table-scoped foreign-key additions with settled effects.
 * @visibility public
 * @example Publishing and rendering an InnoDB reference
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.p (id INT PRIMARY KEY)', 'CREATE TABLE app.c (pid INT)']);
 *     $semantics->analyze('ALTER TABLE app.c ADD CONSTRAINT owner_ref FOREIGN KEY (pid) REFERENCES p(id) ON DELETE CASCADE', $catalog)->toString() // => 'ALTER TABLE `app`.`c` ADD CONSTRAINT `owner_ref` FOREIGN KEY (`pid`) REFERENCES `app`.`p` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE'
 */
final class AddForeignKeys implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ForeignKeyAddition> Ordered names, native field pairs and their index effects
     */
    public readonly array $additions;

    /**
     * Validates every native addition relative to the original complete physical facts.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $additions Checked candidate definitions
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $keys Original physical keys on the affected child and parents
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $constraints Original named constraints in the full owning database
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $additions,
        array $keys,
        array $constraints,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
        public readonly bool $ignore = false,
        ?InnoDbKeyLimit $keyLimit = null,
    ) {
        $checked = Check::listOf($additions, ForeignKeyAddition::class, 'At least one checked native foreign-key addition is required.', 1);
        $originalKeys = Check::listOf($keys, ForeignKeyNativeKey::class, 'Foreign-key addition requires checked original physical keys.');
        $originalConstraints = Check::listOf($constraints, ForeignKeyConstraintIdentity::class, 'Foreign-key addition requires checked original symbols.');
        (new ForeignKeyAdditionSet())->check($owner, $checked, $originalKeys, $originalConstraints, $release, $policy, $ignore, $keyLimit);
        $this->additions = $checked;
    }

    /**
     * Reports the fixed native interpretation of this group.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/add-foreign-keys';
    }

    /**
     * Emits the settled declarations in their original native group order.
     */
    public function toString(): string
    {
        $verb = $this->ignore ? 'ALTER IGNORE TABLE ' : 'ALTER TABLE ';
        $actions = [];
        foreach ($this->additions as $addition) {
            $actions[] = $addition->toString();
        }

        return $verb . $this->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
