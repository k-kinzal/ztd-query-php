<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One factor an ALTER USER factor change names: `n FACTOR`, with the method ADD and MODIFY give it.
 *
 * The number is kept as written; the server accepts only `2` and `3`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Holding the second factor
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2')))->factor->text // => '2'
 */
final class FactorStep implements Node
{
    use Snapshot;

    /**
     * @param Numeral $factor The factor number as written
     * @param Identification|null $identification The authentication method of the factor, for ADD and MODIFY
     */
    public function __construct(public readonly Numeral $factor, public readonly ?Identification $identification = null)
    {
    }

    /**
     * Writes the factor and its method.
     */
    public function render(Output $out): void
    {
        $out->node($this->factor)->keyword('FACTOR')->node($this->identification);
    }
}
