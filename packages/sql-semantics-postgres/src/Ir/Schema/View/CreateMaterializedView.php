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
 * A persistent heap materialized view with a checked defining query.
 *
 * @visibility public
 * @example Declaring an initially unpopulated snapshot
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('CREATE MATERIALIZED VIEW report (n) AS VALUES (8) WITH NO DATA')->withData // => false
 */
final class CreateMaterializedView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The effective ordered catalog heading
     */
    public readonly array $columns;

    /**
     * Closes the exact persistent definition and population policy.
     * @param list<Identifier> $columns The complete effective heading
     */
    public function __construct(
        public readonly ViewName $name,
        public readonly Select|ValuesQuery $query,
        array $columns,
        public readonly bool $ifNotExists = false,
        public readonly bool $withData = true,
    ) {
        $heading = Check::listOf($columns, Identifier::class, 'A persistent view needs a nonempty ordered heading.', 1);
        $width = (new ViewDefinitionBounds())->width($query, $name, ViewCheckOption::None);
        (new ViewHeading())->check($heading, $width);
        $this->columns = $heading;
    }

    /**
     * Answers the profile of the independently checked defining query.
     */
    public function profileId(): string
    {
        return $this->query->profileId();
    }

    /**
     * Emits the exact creation and initial population choices.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $column->quoted();
        }

        return 'CREATE MATERIALIZED VIEW ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->quoted()
            . ' (' . implode(', ', $columns) . ') USING heap AS ' . $this->query->toString()
            . ($this->withData ? ' WITH DATA' : ' WITH NO DATA');
    }
}
