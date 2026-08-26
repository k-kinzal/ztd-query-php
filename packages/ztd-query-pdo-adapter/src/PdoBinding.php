<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use Stringable;

/**
 * One value a caller bound to a placeholder, remembered as PDO was told it.
 *
 * ZTD prepares a statement again every time it is executed, because the SQL it
 * rewrites depends on the parameters. A statement prepared again has nothing
 * bound to it, so every binding a caller made has to be replayed; this is what
 * one of those bindings is.
 *
 * The value stays writable because bindParam() binds a reference: what the
 * caller changes between execute() calls is what the next execute() must send.
 *
 * @phpstan-type BindableValue bool|float|int|resource|string|Stringable|null
 */
final class PdoBinding
{
    /**
     * The value the placeholder carries.
     *
     * A binding made by reference follows the caller's variable, and they may
     * assign anything at all to it between one execution and the next; what
     * PDO can bind is checked where the binding is made, and again by PDO.
     *
     * @var mixed
     */
    public mixed $value;

    /**
     * Remembers one binding.
     *
     * @param BindableValue $value Value the placeholder carries
     * @param int $type One of PDO::PARAM_*, saying how the driver reads the value
     * @param int $maxLength Length reserved for an out parameter, or 0 for none
     * @param BindableValue $driverOptions Driver-specific options the binding was made with
     */
    public function __construct(
        mixed $value,
        public readonly int $type = PDO::PARAM_STR,
        public readonly int $maxLength = 0,
        public readonly mixed $driverOptions = null,
    ) {
        $this->value = $value;
    }

    /**
     * Answers the value where PDO can bind it, and refuses one it cannot.
     *
     * PDO sends a scalar, a stream or nothing at all; anything else it would
     * reject itself, and ZTD would have remembered a binding it can never
     * replay.
     *
     * @param mixed $value Value as the caller passed it
     * @param int|string $parameter Placeholder the value was bound to
     *
     * @return BindableValue The same value, as something bindable
     *
     * @throws ZtdPdoException When PDO cannot bind a value of that type
     */
    public static function bindable(mixed $value, int|string $parameter): mixed
    {
        if ($value === null || is_scalar($value) || is_resource($value) || $value instanceof Stringable) {
            return $value;
        }

        throw new ZtdPdoException(sprintf(
            'Parameter %s cannot be bound to a value of type %s.',
            is_int($parameter) ? sprintf('#%d', $parameter) : sprintf('"%s"', $parameter),
            get_debug_type($value),
        ));
    }
}
