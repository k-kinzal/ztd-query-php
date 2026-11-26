<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Modifier;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;

/**
 * `ALGORITHM [=] DEFAULT | name`: the algorithm the server must use for a table change.
 *
 * The grammar reads the algorithm as an identifier; the server accepts
 * INPLACE and COPY, INSTANT from 8.0 on, and the word DEFAULT spelled as an
 * identifier in 5.6 and 5.7, compared without regard to case. Any other name
 * is the diagnostic UnknownAlterChoice (MYSQL-ALTER-CHOICES-001). The name is
 * kept as written; the keyword DEFAULT is the absent name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility SqlSemantics
 * @example Requesting an in-place change
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('INPLACE'));
 *     $option->algorithm?->value // => 'INPLACE'
 */
final class AlgorithmOption implements AlterOption, AlterModifier
{
    use Snapshot;

    /**
     * @param Name|null $algorithm The algorithm name, or null for the keyword DEFAULT
     */
    public function __construct(public readonly ?Name $algorithm)
    {
    }

    /**
     * Reports an algorithm the server of the release does not know.
     */
    public function deriveOption(Derivation $derivation): void
    {
        (new Choices())->algorithm($this->algorithm, $derivation);
    }

    /**
     * Derives the option as an action of ALTER TABLE.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->deriveOption($derivation);
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALGORITHM')->symbol('=');
        if ($this->algorithm === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->algorithm, NameUse::Label);
        }
    }
}
