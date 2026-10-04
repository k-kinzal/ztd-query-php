<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnNames;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `[LINEAR] KEY [ALGORITHM = n] (columns)`: partitioning by the server's hash of column values.
 *
 * Mirrors PT_part_type_def_key and PT_sub_partition_by_key. An empty column
 * list means the primary key (or the only unique key). ALGORITHM selects the
 * hash function of 5.1 (1) or of 5.5 and later (2). A column name the
 * completely known table does not have is a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-key.html.
 *
 * @visibility public
 * @example Holding a linear key partitioning
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod(true, null, [new \SqlSemantics\Statement\Identifier\Name('id')]);
 *     [$method->linear, $method->columns[0]->value] // => [true, 'id']
 */
final class KeyMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @var list<Name> The partitioning columns in order; empty for the primary key
     */
    public readonly array $columns;

    /**
     * @param bool $linear Whether LINEAR is written
     * @param Numeral|null $algorithm The ALGORITHM number, when written
     * @param list<Name> $columns The partitioning columns in order; empty for the primary key
     */
    public function __construct(public readonly bool $linear, public readonly ?Numeral $algorithm, array $columns)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Key partitioning columns are a list of names.');
    }

    /**
     * Reports the column names the completely known table does not have.
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void
    {
        (new ColumnNames())->check($this->columns, $derivation, $scope);
    }

    /**
     * Writes the method.
     */
    public function render(Output $out): void
    {
        if ($this->linear) {
            $out->keyword('LINEAR');
        }
        $out->keyword('KEY');
        if ($this->algorithm !== null) {
            $out->keyword('ALGORITHM')->symbol('=')->node($this->algorithm);
        }
        (new ColumnNames())->write($out, $this->columns);
    }
}
