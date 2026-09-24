<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * An exclusion constraint: indexed elements with operators, optional included columns, index storage, and a predicate.
 * @visibility public
 * @example Reading the constraint operands
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT ex EXCLUDE USING gist (id WITH =, n WITH &&) INCLUDE (n) WITH (fillfactor = 50) USING INDEX TABLESPACE ts WHERE (id > 0) DEFERRABLE');
 *     $statement->actions[0]->constraint->method // => 'gist'
 *     $statement->actions[0]->constraint->include // => ['n']
 *     $statement->actions[0]->constraint->tablespace // => 'ts'
 *     $statement->actions[0]->constraint->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableImmediate
 */
final class ExclusionConstraint
{
    /**
     * @param non-empty-list<ExclusionElement> $elements
     * @param list<string> $include
     * @param list<Parameter> $parameters
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly array $elements,
        public readonly ?string $name = null,
        public readonly ?string $method = null,
        public readonly array $include = [],
        public readonly array $parameters = [],
        public readonly ?string $tablespace = null,
        public readonly ?Expression $predicate = null,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
    ) {
        Collections::objects(Collections::nonEmpty($elements), ExclusionElement::class);
        Collections::strings($include);
        Collections::objects($parameters, Parameter::class);
        foreach ([$name, $method, $tablespace, ...$include] as $identifier) {
            if ($identifier !== null) {
                CatalogInvariant::identifier($identifier);
            }
        }
    }
}
