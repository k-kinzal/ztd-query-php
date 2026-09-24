<?php

declare(strict_types=1);

use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;

return static function (Registry $functions): void {
    $functions->register('array_fill', static function (array $arguments): ?Domain {
        $value = $arguments[2] ?? Domain::unknown();
        if ($value->soleLiteral()?->value !== '?') {
            return null;
        }

        return Domain::of(new ArrayTerm([new ArrayEntry(null, $value)]));
    });
};
