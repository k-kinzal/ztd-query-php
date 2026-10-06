<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define how a type is passed to and from a procedural language.
 *
 * Rule: PG-TRANSFORM-001. Mirrors `CreateTransformStmt`: replace, type,
 * language and one function per direction, kept in the order written.
 * Source: https://www.postgresql.org/docs/17/sql-createtransform.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the language of a transform
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE TRANSFORM FOR hstore LANGUAGE plpython3u (FROM SQL WITH FUNCTION a(internal), TO SQL WITH FUNCTION b(internal))');
 *     [$operation->statement->language->value, count($operation->statement->functions)] // => ['plpython3u', 2]
 */
final class CreateTransform implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<TransformFunction> One or two functions of different directions, in the order written
     */
    public readonly array $functions;

    /**
     * @param bool $orReplace Whether OR REPLACE is written
     * @param TypeName $type The type
     * @param Name $language The language
     * @param list<TransformFunction> $functions One or two functions of different directions, in the order written
     */
    public function __construct(public readonly bool $orReplace, public readonly TypeName $type, public readonly Name $language, array $functions)
    {
        $this->functions = Check::listOf($functions, TransformFunction::class, 'A transform has one or two functions.', 1);
        Check::input(count($this->functions) === 1 || (count($this->functions) === 2 && $this->functions[0]->direction !== $this->functions[1]->direction), 'A transform has at most one function per direction.');
    }

    /**
     * Derives the type name and the function signatures.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, [$this->type, ...$this->functions]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->orReplace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword('TRANSFORM', 'FOR')->node($this->type)->keyword('LANGUAGE')->name($this->language, NameUse::Column)->symbol('(')->list($this->functions)->symbol(')');
    }
}
