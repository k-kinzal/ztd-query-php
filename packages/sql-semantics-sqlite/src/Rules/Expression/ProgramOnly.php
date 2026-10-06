<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Statement\Node;
use SqlSemantics\Validation\ValueGraph;

/**
 * Reports the expressions that SQLite admits on one side of a trigger program only.
 *
 * Rule: SQLITE-PROGRAM-ONLY-001. The RAISE function may be used within a
 * trigger program only; a bind parameter may be used outside one only. The
 * check walks the structure once and reports each kind at most once per
 * statement. Terminates: the structure is a finite tree walked once.
 * Source: https://sqlite.org/lang_createtrigger.html#the_raise_function,
 * https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ProgramOnly
{
    /**
     * Reports a RAISE function used in a statement that is no trigger program.
     */
    public function outsideProgram(Node $statement, Derivation $derivation): void
    {
        if ($this->holds($statement, Raise::class)) {
            $derivation->report(new Misuse(MisuseRule::RaiseOutsideTrigger));
        }
    }

    /**
     * Reports a bind parameter used in a trigger program or its condition.
     *
     * @param list<Node> $parts The condition and the statements of the program
     */
    public function insideProgram(array $parts, Derivation $derivation): void
    {
        foreach ($parts as $part) {
            if ($this->holds($part, BindParameter::class)) {
                $derivation->report(new Misuse(MisuseRule::ParameterInTrigger));

                return;
            }
        }
    }

    /**
     * Tells whether a structure contains a node of a class.
     *
     * @param class-string $class
     */
    public function holds(Node $node, string $class): bool
    {
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\']))->objects($node) as $object) {
            if ($object instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
