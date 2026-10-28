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
 * `LOCK [=] DEFAULT | name`: how much concurrent access a table change must allow.
 *
 * The grammar reads the lock level as an identifier; the server accepts
 * NONE, SHARED and EXCLUSIVE, and the word DEFAULT spelled as an identifier
 * in 5.6 and 5.7, compared without regard to case. Any other name is the
 * input refusal UnknownAlterChoice (MYSQL-ALTER-CHOICES-001). The name is kept
 * as written; the keyword DEFAULT is the absent name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility public
 * @example Refusing an unknown option name
 *     new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption(new \SqlSemantics\Statement\Identifier\Name('unknown')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Requesting a change that blocks no reader or writer
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption(new \SqlSemantics\Statement\Identifier\Name('NONE')))->lock?->value // => 'NONE'
 */
final class LockOption implements AlterOption, AlterModifier
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|null $lock The lock level, or null for the keyword DEFAULT
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly ?Name $lock, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = true)
    {
        \SqlSemantics\Diagnostic\Check::input((new Choices())->known($lock, true), 'The lock is a known table-change option.');
    }

    /**
     * Reports a lock level the server of the release does not know.
     */
    public function deriveOption(Derivation $derivation): void
    {
        (new Choices())->lock($this->lock, $derivation);
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
        $out->keyword('LOCK');
        if ($this->equals) {
            $out->symbol('=');
        }
        if ($this->lock === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->lock, NameUse::Label);
        }
    }
}
