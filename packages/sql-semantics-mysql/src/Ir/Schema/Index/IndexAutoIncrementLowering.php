<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Detaches the AUTO_INCREMENT dependency of the selected physical key.
 * @visibility SqlSemantics
 */
final class IndexAutoIncrementLowering
{
    /**
     * Selects other backing keys on the same native owner.
     * @param list<IndexDeclaration> $indexes The resolved physical key declarations
     */
    public function resolve(IndexDeclaration $target, array $indexes): ?IndexAutoIncrement
    {
        $field = $target->autoIncrementField;
        if ($field === null) {
            return null;
        }
        $case = new NativeIdentifierCase();
        $targetName = $case->fold($target->name->value);
        $alternates = [];
        foreach ($indexes as $index) {
            if ($index->autoIncrementField?->value === $field->value && $case->fold($index->name->value) !== $targetName) {
                $alternates[] = new Identifier($index->name->value);
            }
        }

        return new IndexAutoIncrement(new Identifier($field->value), $alternates);
    }
}
