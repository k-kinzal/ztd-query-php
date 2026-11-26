<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The native MATCH metadata and independent parent-row updates and deletes.
 * @visibility public
 * @example Describing one cascading event
 *     $effects = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffects(\SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction::Cascade, \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction::NoAction);
 *     $effects->sql() // => ' ON UPDATE CASCADE ON DELETE NO ACTION'
 */
final class ForeignKeyEffects
{
    use Snapshot;

    /**
     * Fixes the metadata and the effective action for each event.
     */
    public function __construct(
        public readonly ForeignKeyAction $update,
        public readonly ForeignKeyAction $delete,
        public readonly ForeignKeyMatch $match = ForeignKeyMatch::None,
    ) {
    }

    /**
     * Emits the two event roles and any native MATCH metadata.
     */
    public function sql(): string
    {
        $match = $this->match === ForeignKeyMatch::None ? '' : ' MATCH ' . $this->match->value;

        return $match . ' ON UPDATE ' . $this->update->value . ' ON DELETE ' . $this->delete->value;
    }
}
