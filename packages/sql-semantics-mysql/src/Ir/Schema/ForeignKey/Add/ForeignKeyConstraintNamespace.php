<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Settles added native constraint names at the enclosing schema boundary.
 * @visibility SqlSemantics
 */
final class ForeignKeyConstraintNamespace
{
    /**
     * Allocates introduced native symbols in declaration order.
     * @param list<ForeignKeyConstraintIdentity> $catalog The complete original constraint coordinates
     * @param non-empty-list<Identifier|null> $requested Explicit symbols or native allocation requests
     * @return non-empty-list<Identifier>
     */
    public function allocate(TableIdentity $owner, array $catalog, array $requested): array
    {
        $case = new NativeIdentifierCase();
        $prefix = $owner->name->table->value . '_ibfk_';
        $used = [];
        $ordinal = 0;
        $generated = in_array(null, $requested, true);
        foreach ($catalog as $identity) {
            if ($identity->owner->name->schema->value !== $owner->name->schema->value) {
                continue;
            }
            $name = $identity->symbol->value;
            $key = $case->fold($name);
            Check::input(!isset($used[$key]), 'The native foreign-key catalog has a repeated symbol.');
            $used[$key] = true;
            if ($generated && $identity->owner->name->table->value === $owner->name->table->value) {
                $ordinal = max($ordinal, $this->ordinal($name, $prefix));
            }
        }
        $result = [];
        foreach ($requested as $written) {
            $symbol = $written ?? new Identifier($prefix . ++$ordinal);
            $key = $case->fold($symbol->value);
            Check::input(preg_match('/\A.{0,64}\z/us', $symbol->value) === 1, 'A native foreign-key name has at most 64 characters.');
            Check::input(!isset($used[$key]), 'A new foreign-key symbol must be unique in the owning native database.');
            $used[$key] = true;
            $result[] = $symbol;
        }

        return $result;
    }

    /**
     * Reads ordinary native generated-name markers under the fixed domain.
     */
    public function ordinal(string $symbol, string $prefix): int
    {
        if (!str_starts_with($symbol, $prefix)) {
            return 0;
        }
        $suffix = substr($symbol, strlen($prefix));
        if (preg_match('/\A[+-]?[1-9][0-9]*\z/D', $suffix) !== 1) {
            return 0;
        }
        Check::input(preg_match('/\A[1-9][0-9]{0,8}\z/D', $suffix) === 1, 'Native foreign-key allocation requires an ordinary generated ordinal.');

        return (int) $suffix;
    }
}
