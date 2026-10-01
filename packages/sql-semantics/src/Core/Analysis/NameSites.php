<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Core\Policy\RelationRules;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

/**
 * Finds every place a statement writes a table name, using the relation rules of its grammar.
 *
 * The statement is walked once, with the common table expressions visible
 * at each value. At each value, the sites the relation rules describe for
 * its form are read first, so a declaration, drop, definition or ignored
 * name is not read again as a plain reference; then every remaining name
 * symbol of the form is a reference.
 *
 * @phpstan-import-type Site from RelationRules
 * @visibility SqlSemantics
 */
final class NameSites
{
    private const KINDS = ['declarations' => ReferenceKind::Declaration, 'drops' => ReferenceKind::Drop, 'commonTableExpressions' => ReferenceKind::CommonTableExpression, 'ignored' => null, 'pairs' => ReferenceKind::Dependency];

    private readonly Scopes $scopes;

    /**
     * Finds name sites in statements of a vocabulary under its relation rules.
     */
    public function __construct(private readonly Vocabulary $vocabulary, private readonly RelationRules $rules, private readonly NameRules $names, private readonly Forms $forms)
    {
        $this->scopes = new Scopes($vocabulary, $rules, $names, $forms);
    }

    /**
     * Finds the name sites of a command, in writing order.
     *
     * A site read at a form is ordered by the value it names, so a target
     * written after a WITH clause comes after the names inside the clause.
     *
     * @return list<NameSite>
     *
     * @throws LogicException When the relation rules name a symbol a form does not have
     */
    public function find(Command $command): array
    {
        $sites = [];
        $consumed = [];
        $order = [];
        foreach ($this->scopes->walk($command) as [$value, $scope]) {
            $order[spl_object_id($value)] ??= count($order);
            $shape = $this->vocabulary->shape($value);
            if ($shape === null || isset($consumed[spl_object_id($value)])) {
                continue;
            }
            $children = $value->children();
            $scopes = $this->scopes->children($value, $scope);
            $taken = [];
            foreach (self::KINDS as $group => $kind) {
                foreach (RelationRules::matching($this->rules->{$group}, $shape['rule'], $shape['symbols']) as $site) {
                    if (isset($site['type']) && !in_array(Writer::render($this->forms->child($shape['symbols'], $children, $this->forms->position($shape['symbols'], $site['type'][0]))), $site['type'][1], true)) {
                        continue;
                    }
                    $conditional = isset($site['conditional']) && $this->forms->conditional($shape['symbols'], $children, $site['conditional']);
                    foreach ($this->named($shape['symbols'], $children, $site, $taken, $consumed) as [$values, $parts, $position]) {
                        if ($kind !== null) {
                            $sites[] = new NameSite($values[0], $parts, $kind, $conditional, $scopes[$this->forms->index($shape['symbols'], $position)], $values);
                        }
                    }
                }
            }
            foreach ($shape['symbols'] as $position => $symbol) {
                if (!in_array($symbol, $this->rules->nameSymbols, true) || isset($taken[$position])) {
                    continue;
                }
                $taken[$position] = true;
                $child = $this->forms->child($shape['symbols'], $children, $position);
                if ($this->holdsNames($child)) {
                    continue;
                }
                $this->consume($consumed, $child);
                $parts = $this->parts($child);
                if ($parts !== []) {
                    $sites[] = new NameSite($child, $parts, ReferenceKind::Dependency, false, $scopes[$this->forms->index($shape['symbols'], $position)]);
                }
            }
        }
        usort($sites, static fn (NameSite $left, NameSite $right): int => $order[spl_object_id($left->value)] <=> $order[spl_object_id($right->value)]);

        return $sites;
    }

    /**
     * Answers the names a site reads, each as the values that write it with its decoded parts and the symbol position it is written at, marking the positions it takes and the values it reads.
     *
     * A pair writes one name as separate values; the values that write
     * nothing, such as an absent schema, are left out.
     *
     * @param list<string> $symbols
     * @param list<Element> $children
     * @param Site $site
     * @param array<int, true> $taken
     * @param array<int, true> $consumed
     * @return list<array{non-empty-list<Element>, non-empty-list<string>, int}>
     *
     * @throws LogicException When the site names a symbol the form does not have
     */
    public function named(array $symbols, array $children, array $site, array &$taken, array &$consumed): array
    {
        if (isset($site['pair'])) {
            $values = [];
            $first = null;
            foreach ($site['pair'] as $symbol) {
                $position = $this->forms->position($symbols, $symbol, $taken);
                $first ??= $position;
                $taken[$position] = true;
                $values[] = $this->forms->child($symbols, $children, $position);
                $this->consume($consumed, $values[count($values) - 1]);
            }
            $parts = array_merge(...array_map($this->parts(...), $values));

            $written = array_values(array_filter($values, static fn (Element $value): bool => Writer::render($value) !== ''));

            return $parts === [] || $first === null || $written === [] ? [] : [[$written, $parts, $first]];
        }
        if (isset($site['name'])) {
            $position = $this->forms->position($symbols, $site['name'], $taken);
            $taken[$position] = true;
            $value = $this->forms->child($symbols, $children, $position);
            $this->consume($consumed, $value);
            $parts = $this->parts($value);

            return $parts === [] ? [] : [[[$value], $parts, $position]];
        }
        $position = $this->forms->position($symbols, $site['names'] ?? '', $taken);
        $taken[$position] = true;
        $list = $this->forms->child($symbols, $children, $position);
        $this->consume($consumed, $list);
        $items = isset($site['list']) ? $this->forms->unfold($list, $site['list'][0], $site['list'][1]) : [$list];

        return array_values(array_filter(array_map(fn (Element $item): array => [[$item], $this->parts($item), $position], $items), static fn (array $named): bool => $named[1] !== []));
    }

    /**
     * Marks a value and everything below it as read by a site, so the walk does not read its names again.
     *
     * @param array<int, true> $consumed
     */
    public function consume(array &$consumed, Element $value): void
    {
        foreach (Traversal::walk($value) as $inner) {
            $consumed[spl_object_id($inner)] = true;
        }
    }

    /**
     * Reports whether a value is a form that writes a table name somewhere inside it, rather than a name itself.
     */
    public function holdsNames(Element $value): bool
    {
        $shape = $this->vocabulary->shape($value);

        return $shape !== null && array_intersect($shape['symbols'], $this->rules->nameSymbols) !== [];
    }

    /**
     * Decodes the parts of a name value, in writing order.
     *
     * @return list<string>
     */
    public function parts(Element $name): array
    {
        $shape = $this->vocabulary->shape($name);
        $children = $name->children();
        if ($shape !== null && isset($this->rules->parts[$shape['rule']][implode(' ', $shape['symbols'])])) {
            $children = array_values(array_intersect_key($children, array_flip($this->rules->parts[$shape['rule']][implode(' ', $shape['symbols'])])));
        }
        if ($children === []) {
            $text = Writer::render($name);

            return $text === '' ? [] : [$this->names->decode($text)];
        }

        return array_merge(...array_map($this->parts(...), $children));
    }
}
