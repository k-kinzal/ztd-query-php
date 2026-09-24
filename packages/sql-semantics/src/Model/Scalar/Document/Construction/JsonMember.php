<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableFunction\Json\Input;

/**
 * One key and value of JSON_OBJECT or JSON_OBJECTAGG; `KEY k VALUE v`, `k VALUE v` and `k : v` are the same member.
 * @visibility public
 * @example Reading a member
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_OBJECT('a' VALUE 1)");
 *     $member = $query->outputs[0]->expression->members[0];
 *     [$member->key->spelling(), $member->value->expression->spelling(), $member->value->format] // => ["'a'", '1', null]
 */
final class JsonMember
{
    /**
     * Binds the key expression to its formatted value.
     */
    public function __construct(public readonly Expression $key, public readonly Input $value)
    {
    }
}
