<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A positional parameter reference, written `$1`, `$2` and so on.
 *
 * Rule: PG-PARAMETER-001. Facts: inside a list that declares parameter
 * types (the parameters of a routine with an SQL-standard body, the types
 * of PREPARE) the parameter has the declared type of its position and can
 * be NULL; a number beyond the parameters of a routine is an undefined
 * parameter, a number beyond the types of PREPARE is inferred from its use.
 * Anywhere else the type and NULL fact depend on the value bound to the
 * parameter, which no context declares. `$0` and a number above
 * 268435455 are undefined parameters. PostgreSQL 17 rejects a number above
 * 2147483647 while scanning; PostgreSQL 16 converts it with C integer
 * conversion, so there the bound parameter is the number reduced to 32 bits.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-PARAMETERS-POSITIONAL,
 * https://www.postgresql.org/docs/17/xfunc-sql.html#XFUNC-SQL-FUNCTION-ARGUMENTS, https://www.postgresql.org/docs/17/sql-prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the missing input of a parameter
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT $1');
 *     [$query->statement->targets[0]->expression->number, $query->field(0)->type->missing[0]->describe()] // => ['1', 'the value bound to parameter $1']
 */
final class PositionalParameter implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param string $number The parameter number as canonical decimal digits
     */
    public function __construct(public readonly string $number)
    {
        Check::input(preg_match('/\A(?:0|[1-9][0-9]*)\z/', $number) === 1, 'A parameter number is canonical decimal digits.');
    }

    /**
     * Answers the number of the parameter the server binds under a release, or null when it binds none.
     */
    public function bound(GrammarRelease $release): ?int
    {
        $long = strlen($this->number) > 18 ? PHP_INT_MAX : (int) $this->number;
        if ($release === GrammarRelease::PostgreSql166) {
            $long &= 0xFFFFFFFF;
            $long = $long >= 0x80000000 ? $long - 0x100000000 : $long;
        }

        return $long >= 1 && $long <= 268435455 ? $long : null;
    }

    /**
     * Gives a result column no name.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives the declared type of the parameter, facts that depend on the bound value, or the diagnostic of an undefined parameter.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $bound = $this->bound($derivation->context->profile->grammar);
        if ($bound === null) {
            $problem = new NoSuchParameter('$' . $this->number);
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }

        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            foreach ($scope->relations as $visible) {
                if (!$visible->relation instanceof ParameterDeclarations) {
                    continue;
                }
                $slot = $visible->shape->slots[$bound - 1] ?? null;
                if ($slot !== null) {
                    return new ScalarFact($slot->type, $slot->nullability);
                }
                if (!$visible->relation->infersUndeclared()) {
                    $problem = new NoSuchParameter('$' . $bound);
                    $derivation->report($problem);

                    return new ScalarFact(new Invalid($problem), Nullability::Dependent);
                }

                break 2;
            }
        }

        return new ScalarFact(new Dependent([new UnboundParameter('$' . $bound)]), Nullability::Dependent);
    }

    /**
     * Writes the parameter marker.
     */
    public function render(Output $out): void
    {
        $out->spelled('$' . $this->number);
    }
}
