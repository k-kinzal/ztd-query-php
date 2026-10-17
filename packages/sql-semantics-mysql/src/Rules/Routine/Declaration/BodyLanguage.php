<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Statement\Node;

/**
 * Relates a routine's effective language to its body representation.
 *
 * SQL is the default. The last LANGUAGE characteristic wins, including an
 * identifier spelling SQL in any letter case. SQL bodies contain instructions;
 * external bodies contain opaque code. Observed on MySQL 8.1 through 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyLanguage
{
    /**
     * Refuses a body whose representation contradicts its declared language.
     *
     * @param list<Characteristic> $characteristics The characteristics in written order
     */
    public function check(Node $body, array $characteristics): void
    {
        Check::input($this->compatible($body, $characteristics), 'The routine language and body representation agree.');
    }

    /**
     * Answers whether the effective language describes the body representation.
     *
     * @param list<Characteristic> $characteristics The characteristics in written order
     */
    public function compatible(Node $body, array $characteristics): bool
    {
        $external = false;
        foreach ($characteristics as $characteristic) {
            if ($characteristic instanceof RoutineLanguage) {
                $external = strcasecmp($characteristic->external->value ?? 'SQL', 'SQL') !== 0;
            }
        }
        return ($body instanceof ExternalBody) === $external;
    }
}
