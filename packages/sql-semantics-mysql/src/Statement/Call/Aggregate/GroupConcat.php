<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Aggregate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of GROUP_CONCAT(): the concatenated non-NULL values of a group, with DISTINCT, an ordering and a separator.
 *
 * Rule: MYSQL-GROUP-CONCAT-001. The arguments of one row are concatenated,
 * the rows in the written order, separated by the separator (a comma by
 * default). The result is a string in the character set of the arguments,
 * binary when one is binary; it is NULL when a group has no non-NULL row.
 * Its length is cut to group_concat_max_len. The grammar of MySQL 8.0 and
 * later accepts OVER, which the server rejects (ER_NOT_SUPPORTED_YET).
 * Terminates: the parts are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html#function_group-concat.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing GROUP_CONCAT()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT GROUP_CONCAT(DISTINCT 'a' SEPARATOR ';')");
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->nullability] // => ['VARCHAR', \SqlSemantics\Statement\Type\Nullability::Nullable]
 */
final class GroupConcat implements SetFunction
{
    use Snapshot;

    /**
     * @var list<Scalar> The concatenated expressions in order
     */
    public readonly array $arguments;

    /**
     * @var list<OrderItem> The ordering of the rows
     */
    public readonly array $order;

    /**
     * @param list<Scalar> $arguments The concatenated expressions in order; at least one
     * @param bool $distinct Whether DISTINCT is written
     * @param list<OrderItem> $order The ordering of the rows
     * @param Text|null $separator The separator, when written
     * @param Name|WindowSpecification|null $over The window written after OVER
     */
    public function __construct(
        array $arguments,
        public readonly bool $distinct = false,
        array $order = [],
        public readonly ?Text $separator = null,
        public readonly Name|WindowSpecification|null $over = null,
    ) {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'GROUP_CONCAT takes at least one expression.', 1);
        $this->order = Check::listOf($order, OrderItem::class, 'An ordering is a list of ordering items.');
    }

    /**
     * Tells whether the call aggregates its query block: it has no window.
     */
    public function aggregates(): bool
    {
        return $this->over === null;
    }

    /**
     * Derives the parts and the result, and reports a window.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $environment = $this->aggregates() ? (new HavingScope())->leave($environment) : $environment;
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument, $derivation, $environment);
        }
        foreach ($this->order as $item) {
            (new Arguments())->one($item->expression, $derivation, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);
        if ($this->over !== null) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::GroupConcat));
        }

        return new ScalarFact((new ResultTyping())->type('S', $facts), Nullability::Nullable);
    }

    /**
     * Writes the call and its window.
     */
    public function render(Output $out): void
    {
        $out->keyword('GROUP_CONCAT')->glue()->symbol('(');
        if ($this->distinct) {
            $out->keyword('DISTINCT');
        }
        $out->list($this->arguments);
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        if ($this->separator !== null) {
            $out->keyword('SEPARATOR')->node($this->separator);
        }
        $out->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
