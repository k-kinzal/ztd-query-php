<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Modifier;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `ALGORITHM [=] DEFAULT | name`: the algorithm the server must use for a table change.
 *
 * The grammar reads the algorithm as an identifier; the server accepts
 * INPLACE and COPY, INSTANT from 8.0 on, and the word DEFAULT spelled as an
 * identifier in 5.6 and 5.7, compared without regard to case. Any other name
 * is retained with a definition condition (MYSQL-ALTER-CHOICES-001). The name is
 * kept as written; the keyword DEFAULT is the absent name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility public
 * @example Retaining an unknown option name
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(new \SqlSemantics\Statement\Identifier\Name('unknown')))->algorithm?->value // => 'unknown'
 * @example Requesting an in-place change
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(new \SqlSemantics\Statement\Identifier\Name('INPLACE'));
 *     $option->algorithm?->value // => 'INPLACE'
 */
final class AlgorithmOption implements AlterOption, AlterModifier
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|null $algorithm The algorithm name, or null for the keyword DEFAULT
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly ?Name $algorithm, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = true)
    {
    }

    /**
     * Reports an algorithm the server of the release does not know.
     */
    public function deriveOption(Derivation $derivation): void
    {
        (new Choices())->algorithm($this->algorithm, $derivation, $this);
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
        $out->keyword('ALGORITHM');
        if ($this->equals) {
            $out->symbol('=');
        }
        if ($this->algorithm === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->algorithm, NameUse::Label);
        }
    }
}
