<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\Term;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Type\TypeShape;

/**
 * Laravel identifier wrapping and ordered SQL fragments for the selected grammar.
 *
 * @visibility root
 */
final class Grammar
{
    /**
     * The framework class of a raw expression.
     */
    public const EXPRESSION = 'Illuminate\Database\Query\Expression';

    /**
     * Uses the configured framework SQL grammar.
     */
    public function __construct(public readonly ?\SqlCatalog\Core\Sql\Dialect $dialect)
    {
    }

    /**
     * Wraps an identifier; expressions already represent SQL and unresolved names stay open where they are.
     */
    public function wrap(Domain $value): Domain
    {
        $raw = $value->soleObject();
        if ($raw instanceof ObjectTerm && $raw->className === self::EXPRESSION) {
            return QueryState::from($raw)->get('sql');
        }
        if ($this->dialect === null) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel connection dialect is unresolved');
        }
        $terms = [];
        foreach ($value->terms as $term) {
            $name = $term instanceof LiteralTerm ? $term->value : null;
            $terms = array_merge($terms, is_string($name) ? $this->wrapName($name)->terms : $this->opened($term)->terms);
        }

        return Domain::fromTerms($terms, $value->widened, $value->combined);
    }

    /**
     * A resolved identifier written with the grammar's delimiters, aliases and qualifiers.
     */
    public function wrapName(string $name): Domain
    {
        if ($this->dialect === null) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel connection dialect is unresolved');
        }
        if (str_contains($name, '->')) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel JSON selectors are not modelled');
        }
        $quote = $this->dialect->identifierQuote();
        $alias = preg_split('/\s+as\s+/i', $name);
        if ($alias !== false && count($alias) >= 2) {
            return $this->wrapName($alias[0])->concat(Domain::literal(' as '))->concat(Domain::literal($alias[1] === '*' ? '*' : $quote . str_replace($quote, $quote . $quote, $alias[1]) . $quote));
        }
        $parts = array_map(static fn (string $part): string => $part === '*' ? '*' : $quote . str_replace($quote, $quote . $quote, $part) . $quote, explode('.', $name));

        return Domain::literal(implode('.', $parts));
    }

    /**
     * The gap an unresolved identifier leaves, keeping where the value came from.
     */
    public function opened(Term $term): Domain
    {
        if ($term instanceof LiteralTerm) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Laravel identifier is not a string');
        }

        return Domain::of($term);
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

        return $object?->className === self::EXPRESSION ? QueryState::from($object)->get('sql') : Domain::literal('?');
    }

    /**
     * @param list<Domain> $values
     * @return list<Domain> Values that occupy actual placeholders.
     */
    public function bindings(array $values): array
    {
        return array_values(array_filter($values, static fn (Domain $value): bool => $value->soleObject()?->className !== self::EXPRESSION));
    }

    /**
     * The gap a placeholder list of unknown length leaves, keeping where the values came from.
     */
    public function placeholders(Domain $values): Domain
    {
        $term = count($values->terms) === 1 ? $values->terms[0] : null;
        if ($term instanceof OpaqueTerm) {
            return Domain::of(new OpaqueTerm(TypeShape::of(['string']), $term->origin, $term->expression, $term->variable));
        }

        return Domain::opaque(TypeShape::of(['string']), $term === null ? Origin::Branch : Origin::Unresolved, 'Laravel placeholder list of unknown length');
    }

    /**
     * One element of a list whose contents are unknown, keeping where the list came from.
     */
    public function element(Domain $values): Domain
    {
        $term = count($values->terms) === 1 ? $values->terms[0] : null;
        if ($term instanceof OpaqueTerm) {
            return Domain::of(new OpaqueTerm(TypeShape::unknown(), $term->origin, $term->expression, $term->variable));
        }

        return Domain::unknown('Laravel binding');
    }

    /**
     * @return list<Domain>|null The values a raw fragment binds, one per placeholder when the array is unknown.
     */
    public function rawBindings(Domain $sql, Domain $values): ?array
    {
        $array = $values->soleArray();
        if ($array !== null && $array->complete) {
            return array_map(static fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): Domain => $entry->value, $array->entries);
        }
        $text = $sql->soleLiteral()?->value;
        if (!is_string($text) || $values->soleObject() !== null) {
            return null;
        }

        return array_fill(0, substr_count($text, '?'), $this->element($values));
    }
}
