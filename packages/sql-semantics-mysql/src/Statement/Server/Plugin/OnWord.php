<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The word ON as the value of a component variable in INSTALL COMPONENT … SET.
 *
 * The grammar reads it as the string 'ON' in the system character set
 * (install_set_rvalue: Item_string "ON"). Rule: MYSQL-ON-WORD-001. Facts: a
 * known VARCHAR that is not NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-component.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Setting a component variable to ON
 *     $install = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSTALL COMPONENT 'file://c' SET c.v = ON");
 *     $install->settings[0]->value instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord // => true
 */
final class OnWord implements Operand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Derives the string the word stands for.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(new \SqlSemantics\Platform\MySql\Statement\Type\StringFamily()), Nullability::NotNull);
    }

    /**
     * Writes the word.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
    }
}
