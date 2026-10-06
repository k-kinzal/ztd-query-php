<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW PROFILE: the stages of one profiled statement of the session and their resource use.
 *
 * Rule: MYSQL-SHOW-PROFILE-001. The rows always have `Status` and
 * `Duration`; each section written adds its columns, in the fixed order of
 * the layout of MYSQL-SHOW-ROWS-001 whatever order the sections are written
 * in, and a section written twice adds its columns once. FOR QUERY selects a
 * statement of SHOW PROFILES, by default the last one. LIMIT selects rows
 * as in SELECT; its operands are derived at a position that sees no
 * relation. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-profile.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the columns of the sections
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW PROFILE SWAPS, CPU FOR QUERY 2');
 *     [array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): ?string => $field->name?->value, iterator_to_array($show->fields())), $show->toString()] // => [['Status', 'Duration', 'CPU_user', 'CPU_system', 'Swaps'], 'SHOW PROFILE SWAPS, CPU FOR QUERY 2']
 */
final class ShowProfile implements Statement
{
    use Snapshot;

    /**
     * @var list<ProfileSection> The sections in the order written
     */
    public readonly array $sections;

    /**
     * @param list<ProfileSection> $sections The sections in the order written
     * @param Numeral|null $query The number of the statement after FOR QUERY
     * @param Limit|null $limit The LIMIT clause
     */
    public function __construct(array $sections = [], public readonly ?Numeral $query = null, public readonly ?Limit $limit = null)
    {
        $this->sections = Check::listOf($sections, ProfileSection::class, 'The sections of SHOW PROFILE are a list.');
    }

    /**
     * Derives the rows of the sections written and the LIMIT operands.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        $facts->limit($derivation, $this->limit);
        $all = $facts->shape($derivation, Report::Profile)->slots;
        $positions = [0 => true, 1 => true];
        foreach ($this->sections as $section) {
            foreach ($section->positions() as $position) {
                $positions[$position] = true;
            }
        }
        ksort($positions);
        $slots = [];
        foreach (array_keys($positions) as $position) {
            $slots[] = $all[$position];
        }
        $derivation->output($facts->query(new RowShape($slots), $derivation->context->columnNames));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PROFILE');
        foreach ($this->sections as $index => $section) {
            if ($index > 0) {
                $out->symbol(',');
            }
            $out->keyword(...explode(' ', $section->value));
        }
        if ($this->query !== null) {
            $out->keyword('FOR', 'QUERY')->node($this->query);
        }
        $out->node($this->limit);
    }
}
