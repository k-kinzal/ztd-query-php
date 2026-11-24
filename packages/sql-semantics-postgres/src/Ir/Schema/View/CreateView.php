<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A persistent ordinary view and its checked PostgreSQL definition.
 *
 * @visibility public
 * @example Constructing a named view over a constant table
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('CREATE VIEW report (n) AS VALUES (8),(8)')->toString() // => 'CREATE VIEW "public"."report" ("n") AS VALUES (CAST(8 AS pg_catalog.int4)), (CAST(8 AS pg_catalog.int4))'
 */
final class CreateView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The effective ordered catalog heading
     */
    public readonly array $columns;

    /**
     * Closes the native query, complete heading and policies before publication.
     * @param list<Identifier> $columns The complete effective heading
     */
    public function __construct(
        public readonly ViewName $name,
        public readonly Select|ValuesQuery $query,
        array $columns,
        public readonly bool $replace = false,
        public readonly ?bool $securityBarrier = null,
        public readonly ?bool $securityInvoker = null,
        public readonly ViewCheckOption $checkOption = ViewCheckOption::None,
    ) {
        $heading = Check::listOf($columns, Identifier::class, 'A persistent view needs a nonempty ordered heading.', 1);
        $width = (new ViewDefinitionBounds())->width($query, $name, $checkOption, true);
        (new ViewHeading())->check($heading, $width);
        $this->columns = $heading;
    }

    /**
     * Returns the native profile of the checked defining query.
     */
    public function profileId(): string
    {
        return $this->query->profileId();
    }

    /**
     * Emits the persistent view and its exact clause choices.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $column->quoted();
        }
        $options = [];
        if ($this->securityBarrier !== null) {
            $options[] = 'security_barrier=' . ($this->securityBarrier ? 'true' : 'false');
        }
        if ($this->securityInvoker !== null) {
            $options[] = 'security_invoker=' . ($this->securityInvoker ? 'true' : 'false');
        }
        $settings = $options === [] ? '' : ' WITH (' . implode(', ', $options) . ')';
        $check = $this->checkOption === ViewCheckOption::None ? '' : ' WITH ' . $this->checkOption->value . ' CHECK OPTION';

        return 'CREATE ' . ($this->replace ? 'OR REPLACE ' : '') . 'VIEW ' . $this->name->quoted()
            . ' (' . implode(', ', $columns) . ')' . $settings . ' AS ' . $this->query->toString() . $check;
    }
}
