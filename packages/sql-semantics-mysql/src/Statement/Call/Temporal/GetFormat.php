<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Temporal;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;

/**
 * A call of GET_FORMAT(kind, standard): a format string for DATE_FORMAT and STR_TO_DATE.
 *
 * Rule: MYSQL-GET-FORMAT-001. The result is a character string; it is NULL
 * for a standard name the server does not know. Terminates: the operand is
 * a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_get-format.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class GetFormat implements Scalar
{
    use Snapshot;

    /**
     * @param TemporalFormat $format The kind of value
     * @param Scalar $standard The name of the standard
     */
    public function __construct(public readonly TemporalFormat $format, public readonly Scalar $standard)
    {
    }

    /**
     * Derives the operand; the result is a character string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ResultTyping())->fact('TY', [(new Arguments())->one($this->standard, $derivation, $environment)]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('GET_FORMAT')->glue()->symbol('(')->keyword($this->format->value)->symbol(',')->node($this->standard)->symbol(')');
    }
}
