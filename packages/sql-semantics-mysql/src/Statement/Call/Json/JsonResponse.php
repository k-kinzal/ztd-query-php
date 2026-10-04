<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The response of an ON EMPTY or ON ERROR clause: raise an error, return NULL, or return a default literal.
 *
 * The holder of the clause derives the default and writes ON EMPTY or ON ERROR.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value,
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility public
 * @example Holding a default response
 *     $response = new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse(\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind::Default, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'));
 *     $response->default?->text // => '0'
 * @example Refusing a default without DEFAULT
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse(\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind::Null, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JsonResponse implements Node
{
    use Snapshot;

    /**
     * @param JsonResponseKind $kind The response
     * @param Scalar|null $default The literal of DEFAULT; present exactly for DEFAULT
     */
    public function __construct(public readonly JsonResponseKind $kind, public readonly ?Scalar $default = null)
    {
        Check::input(($default !== null) === ($kind === JsonResponseKind::Default), 'A response has a literal exactly when it is DEFAULT.');
    }

    /**
     * Writes the response.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->default);
    }
}
