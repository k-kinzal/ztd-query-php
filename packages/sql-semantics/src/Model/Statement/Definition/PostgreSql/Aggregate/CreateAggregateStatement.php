<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\AggregateParameter;
use SqlSemantics\Model\Definition\Routine\OrderedSetAggregate;
use SqlSemantics\Model\Definition\Routine\OrdinaryAggregate;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates or replaces an aggregate from its signature and its state transition definition; the old BASETYPE syntax binds to the same signature forms.
 * @visibility public
 * @example Creating an aggregate with a moving-aggregate mode
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE AGGREGATE app.total(integer) (SFUNC = int4pl, STYPE = integer, MSFUNC = int4pl, MINVFUNC = int4mi, MSTYPE = integer, PARALLEL = safe)');
 *     $statement->aggregate->name->parts // => ['app', 'total']
 *     $statement->options[5]->value // => 'safe'
 *     $statement->orReplace // => true
 * @example Binding the old syntax to a zero-argument aggregate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE AGGREGATE tally (BASETYPE = 'ANY', SFUNC1 = int8inc, STYPE1 = bigint, INITCOND1 = '0')");
 *     $statement->toString() // => 'CREATE AGGREGATE "tally"(*)(SFUNC = "int8inc", STYPE = bigint, INITCOND = \'0\')'
 */
final class CreateAggregateStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ZeroArgumentAggregate|OrdinaryAggregate|OrderedSetAggregate $aggregate, public readonly array $options, public readonly bool $orReplace = false)
    {
        TypeSystemInvariant::dialect($origin);
        $parameters = match (true) {
            $aggregate instanceof ZeroArgumentAggregate => [],
            $aggregate instanceof OrdinaryAggregate => $aggregate->parameters,
            $aggregate instanceof OrderedSetAggregate => [...$aggregate->direct, ...$aggregate->ordered],
        };
        if (array_filter($parameters, static fn (AggregateParameter $parameter): bool => $parameter->setOf) !== []) {
            throw new InvalidStructure('Aggregates cannot accept set arguments.');
        }
        DefinitionOptions::validate(Collections::nonEmpty($options), AggregateAttribute::cases());
        DefinitionOptions::present($options);
        DefinitionOptions::require($options, [AggregateAttribute::Sfunc, AggregateAttribute::Stype]);
        self::moving($options);
        if (DefinitionOptions::value($options, AggregateAttribute::Hypothetical) === true && !$aggregate instanceof OrderedSetAggregate) {
            throw new InvalidStructure('Only ordered-set aggregates can be hypothetical.');
        }
        parent::__construct($origin);
    }

    /**
     * MSTYPE requires MSFUNC and MINVFUNC; without MSTYPE no moving-aggregate attribute takes effect.
     * @param list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public static function moving(array $options): void
    {
        if (DefinitionOptions::find($options, AggregateAttribute::Mstype) !== null) {
            DefinitionOptions::require($options, [AggregateAttribute::Msfunc, AggregateAttribute::Minvfunc]);
            return;
        }
        foreach ($options as $option) {
            if ($option->attribute instanceof AggregateAttribute && $option->attribute->moving() && $option->value !== false && $option->value !== 0) {
                throw new InvalidStructure('The ' . $option->attribute->spelling() . ' attribute requires MSTYPE.');
            }
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->aggregate, $this->options, $this->orReplace);
    }

    /**
     * Replaces the aggregate signature.
     */
    public function withAggregate(ZeroArgumentAggregate|OrdinaryAggregate|OrderedSetAggregate $aggregate): self
    {
        return $this->changed(new self($this->origin, $aggregate, $this->options, $this->orReplace));
    }

    /**
     * Replaces the aggregate attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->aggregate, $options, $this->orReplace));
    }

    /**
     * Chooses whether an existing aggregate is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->aggregate, $this->options, $orReplace));
    }
}
