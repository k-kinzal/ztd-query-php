<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

/**
 * The word ON as the value of a component variable in INSTALL COMPONENT … SET.
 *
 * The grammar reads it as the string 'ON' in the system character set
 * (install_set_rvalue: Item_string "ON"). Rule: MYSQL-ON-WORD-001. Facts: a
 * known VARCHAR that is not NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-component.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OnWord implements Scalar
{
    use Snapshot;

    /**
     * Derives the string the word stands for.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(new Character(CharacterKind::VarChar)), Nullability::NotNull);
    }

    /**
     * Writes the word.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON');
    }
}
