<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One factor an ALTER USER factor change names: `n FACTOR`, with the method ADD and MODIFY give it.
 *
 * The factor type admits exactly the second and third factors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Holding the second factor
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second))->factor->value // => '2'
 */
final class FactorStep implements Node
{
    use Snapshot;

    /**
     * @param Factor $factor The additional authentication factor
     * @param Identification|null $identification The authentication method of the factor, for ADD and MODIFY
     */
    public function __construct(public readonly Factor $factor, public readonly ?Identification $identification = null)
    {
    }

    /**
     * Writes the factor and its method.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->factor->value)->keyword('FACTOR')->node($this->identification);
    }
}
