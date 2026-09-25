<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Laravel identifier wrapping and ordered SQL fragments for the selected grammar.
 *
 * @visibility root
 */
final class Grammar
{
    /**
     * Uses the configured framework SQL grammar.
     */
    public function __construct(public readonly ?string $dialect)
    {
    }

    /**
     * Wraps an identifier; expressions already represent SQL.
     */
    public function wrap(Domain $value): Domain
    {
        $raw = $value->soleObject();
        if ($raw instanceof ObjectTerm && $raw->className === 'Illuminate\Database\Query\Expression') {
            return QueryState::from($raw)->get('sql');
        }
        $name = $value->soleLiteral()?->value;
        if (!is_string($name) || !in_array($this->dialect, ['mysql', 'pgsql', 'sqlite'], true)) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel identifier or connection dialect is unresolved');
        }
        if (str_contains($name, '->')) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel JSON selectors are not modelled');
        }
        $quote = $this->dialect === 'mysql' ? '`' : '"';
        $alias = preg_split('/\s+as\s+/i', $name);
        if ($alias !== false && count($alias) >= 2) {
            return $this->wrap(Domain::literal($alias[0]))->concat(Domain::literal(' as '))->concat(Domain::literal($alias[1] === '*' ? '*' : $quote . str_replace($quote, $quote . $quote, $alias[1]) . $quote));
        }
        $parts = array_map(static fn (string $part): string => $part === '*' ? '*' : $quote . str_replace($quote, $quote . $quote, $part) . $quote, explode('.', $name));

        return Domain::literal(implode('.', $parts));
    }

    /**
     * @param list<Domain> $parts Joins fragments without discarding holes or alternatives.
     */
    public function join(array $parts, string $separator = ', '): Domain
    {
        $result = Domain::literal('');
        foreach ($parts as $index => $part) {
            $result = $result->concat(Domain::literal($index === 0 ? '' : $separator))->concat($part);
        }

        return $result;
    }

    /**
     * A raw expression, or the marker for a bound value.
     */
    public function parameter(Domain $value): Domain
    {
        $object = $value->soleObject();

        return $object?->className === 'Illuminate\Database\Query\Expression' ? QueryState::from($object)->get('sql') : Domain::literal('?');
    }

    /**
     * @param list<Domain> $values
     * @return list<Domain> Values that occupy actual placeholders.
     */
    public function bindings(array $values): array
    {
        return array_values(array_filter($values, static fn (Domain $value): bool => $value->soleObject()?->className !== 'Illuminate\Database\Query\Expression'));
    }
}
