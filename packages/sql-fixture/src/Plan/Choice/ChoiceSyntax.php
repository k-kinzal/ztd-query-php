<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\Exception\UnexpectedPlanTokenException;
use SqlFixture\Plan\Parsing\PlanStatements;
use SqlFixture\Plan\Parsing\RelationCursor;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;

/**
 * Parses and prints the fixture-specific choice extension to relation syntax.
 * @visibility root
 */
final class ChoiceSyntax
{
    /**
     * Reads a complete choice declaration.
     * @throws UnexpectedPlanTokenException
     */
    public function parse(string $source): RelationChoice
    {
        if (preg_match('/^choice\s+(.+?)\s*\{/s', $source, $match) !== 1) {
            throw new UnexpectedPlanTokenException($source, 0, 'choice table.column { ... }');
        }
        $choice = RelationChoice::on(trim($match[1]));
        $cursor = new RelationCursor($source, strlen($match[0]));
        $cursor->skipWhitespace();
        while ($cursor->peek() !== '}') {
            $fallback = substr($source, $cursor->offset, 9) === 'otherwise';
            if ($fallback) {
                $cursor->offset += 9;
                $value = null;
            } else {
                $value = (new ChoiceLiteral())->read($cursor);
            }
            $cursor->skipWhitespace();
            $relations = $this->readRelations($cursor);
            $choice = $fallback ? $choice->otherwise(...$relations) : $choice->when($value, ...$relations);
            $cursor->skipWhitespace();
        }
        $cursor->offset++;
        $cursor->skipWhitespace();
        $cursor->expectEnd();
        return $choice;
    }

    /**
     * Reads the ordinary relations enclosed in one branch.
     * @return list<Relation>
     * @throws UnexpectedPlanTokenException
     */
    public function readRelations(RelationCursor $cursor): array
    {
        if ($cursor->peek() !== '{') {
            throw new UnexpectedPlanTokenException($cursor->source, $cursor->offset, "'{' before the branch relations");
        }
        $start = ++$cursor->offset;
        while (($character = $cursor->peek()) !== null && $character !== '}') {
            if (in_array($character, ["'", '"', '`'], true)) {
                $cursor->offset = (new PlanStatements())->quotedEnd($cursor->source, $cursor->offset);
            }
            $cursor->offset++;
        }
        if ($cursor->peek() === null) {
            throw new UnexpectedPlanTokenException($cursor->source, $cursor->offset, "'}' after the branch relations");
        }
        $body = trim(substr($cursor->source, $start, $cursor->offset++ - $start));
        if ($body === '') {
            return [];
        }
        $plan = (new PlanParser())->parse($body);
        foreach ($plan->parts() as $part) {
            if (!$part instanceof Relation) {
                throw new UnexpectedPlanTokenException($cursor->source, $start, 'ordinary relations inside a branch');
            }
        }
        return $plan->relations;
    }

    /**
     * Writes a canonical choice while retaining literal types.
     */
    public function print(RelationChoice $choice): string
    {
        $branches = [];
        foreach ($choice->branches() as $branch) {
            $label = $branch === $choice->fallback ? 'otherwise' : (new ChoiceLiteral())->print($branch->value);
            $relations = array_map(static fn (Relation $relation): string => (new PlanPrinter())->printRelation($relation), $branch->relations);
            $branches[] = $label . ' { ' . implode(', ', $relations) . ' }';
        }
        return 'choice ' . $choice->discriminator->toString() . ' { ' . implode(' ', $branches) . ' }';
    }
}
