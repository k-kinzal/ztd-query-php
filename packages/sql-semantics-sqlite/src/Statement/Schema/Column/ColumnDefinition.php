<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\NonConstantDefault;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnProblem;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One column of a table definition: its name, its declared type and its constraints in written order.
 *
 * Rule: SQLITE-COLUMN-DEFINITION-001. The declared type is optional and is
 * kept word by word with its quoting, because SQLite records the written
 * text. The constraints are a list in written order; nothing limits how
 * often a kind of constraint occurs. The numbers written as type arguments
 * and the operands of the constraints are derived inside the table
 * definition. Diagnostics: a DEFAULT expression that is not constant
 * (SQLITE-DEFINITION-LIMITS-001), and a DEFAULT on a generated column.
 * Source: https://sqlite.org/syntax/column-def.html,
 * https://sqlite.org/lang_createtable.html#the_default_clause,
 * https://sqlite.org/gencol.html#limitations. Status: Implemented.
 *
 * @visibility public
 * @example Reading a column definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (price DECIMAL(10, 2) NOT NULL DEFAULT 0)');
 *     $column = $create->statement->columns[0];
 *     [$column->name->value, $column->type?->text(), count($column->constraints), $column->notNull()] // => ['price', 'DECIMAL', 2, true]
 */
final class ColumnDefinition implements Node
{
    use Snapshot;

    /**
     * @var list<ColumnConstraint> The constraints in written order
     */
    public readonly array $constraints;

    /**
     * @param Name $name The column name
     * @param TypeName|null $type The declared type
     * @param list<ColumnConstraint> $constraints The constraints in written order
     */
    public function __construct(public readonly Name $name, public readonly ?TypeName $type = null, array $constraints = [])
    {
        $this->constraints = Check::listOf($constraints, ColumnConstraint::class, 'Column constraints are an ordered list of column constraints.');
    }

    /**
     * Tells whether a NOT NULL constraint is written for the column.
     */
    public function notNull(): bool
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint instanceof NotNull) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the first PRIMARY KEY constraint written for the column.
     */
    public function primaryKey(): ?ColumnPrimaryKey
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint instanceof ColumnPrimaryKey) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * Answers the first generated column expression written for the column.
     */
    public function generated(): ?Generated
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint instanceof Generated) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * Derives the type arguments and the operands of the constraints inside the table definition, and reports a default SQLite rejects.
     */
    public function deriveColumn(Derivation $derivation, ConstraintScope $scope): void
    {
        foreach ($this->type->arguments ?? [] as $argument) {
            $derivation->scalar($argument->number, $scope->constant);
        }
        $default = false;
        foreach ($this->constraints as $constraint) {
            $constraint->deriveConstraint($derivation, $scope);
            if ($constraint instanceof DefaultExpression && (new Limits())->nonConstant($constraint->expression)) {
                $derivation->report(new NonConstantDefault($this->name));
            }
            $default = $default || $constraint instanceof DefaultExpression || $constraint instanceof DefaultLiteral || $constraint instanceof DefaultWord;
        }
        if ($default && $this->generated() !== null) {
            $derivation->report(new GeneratedColumnProblem(GeneratedColumnFlaw::WithDefault, $this->name));
        }
    }

    /**
     * Writes the name, the declared type and the constraints.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->node($this->type);
        foreach ($this->constraints as $constraint) {
            $out->node($constraint);
        }
    }
}
