<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * Places legacy client-encoding checks among ordered assignment preparations.
 * Modern releases prepare all expression values before checking client settings.
 * Observed on MySQL 5.6.51, 5.7.44 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class SetEncodings
{
    /**
     * Adds known, unusable client encodings at their SET item's semantic position.
     *
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function located(Node $statement, Locator $locator, Session $session, array $located): array
    {
        if (!$statement instanceof SetVariables || !$session->settings()->legacy()) {
            return $located;
        }
        foreach ($statement->items as $item) {
            if (!$item instanceof SetNames && !$item instanceof SetCharacterSet) {
                continue;
            }
            $charset = $item->charset->name === null ? null : Charset::named($item->charset->name->value);
            $place = $locator->place($item);
            if ($charset !== null && $charset->minLength() > 1 && $place !== null) {
                $error = AdministrationError::WrongValueForVariable->error('character_set_client', $charset->nameIn($session->settings()->release()));
                $located[spl_object_id($error)] = [$error, $place];
            }
        }
        return $located;
    }
}
