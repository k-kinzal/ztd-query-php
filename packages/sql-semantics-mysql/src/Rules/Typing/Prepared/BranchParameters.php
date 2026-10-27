<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding;
use SqlSemantics\Statement\Scalar;

/**
 * Derives modern prepared marker types from the other result branches.
 * Integer branches provide BIGINT; text branches retain the preparation
 * VARCHAR capacity in their collation. An all-NULL sibling set provides
 * binary VARCHAR. Observed through prepared queries on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BranchParameters
{
    /**
     * @return list<Scalar> Result branches, without CASE conditions or the IF predicate
     */
    public function operands(Scalar $expression): array
    {
        return match (true) {
            $expression instanceof CaseExpression => [...array_map(static fn ($branch): Scalar => $branch->result, $expression->branches), ...($expression->else === null ? [] : [$expression->else])],
            $expression instanceof KeywordCall && $expression->function === KeywordFunction::If => array_slice($expression->arguments, 1),
            $expression instanceof KeywordCall && $expression->function === KeywordFunction::Coalesce => $expression->arguments,
            $expression instanceof FunctionCall && $expression->schema === null && strtoupper($expression->name->value) === 'IFNULL' => array_map(static fn ($argument): Scalar => $argument->expression, $expression->arguments),
            default => [],
        };
    }

    /**
     * @return array<int, Domain> Inferred domains keyed by the resolved marker occurrence
     */
    public function infer(Scalar $expression, Statement $operation): array
    {
        $markers = [];
        $domains = [];
        foreach ($this->operands($expression) as $branch) {
            while ($branch instanceof Grouped) {
                $branch = $branch->operand;
            }
            $fact = $branch->scalarInformation();
            if ($branch instanceof Parameter && $fact->resolution instanceof ParameterBinding) {
                $markers[] = $fact->resolution->position;
            } else {
                $domain = (new Precision())->domain($fact->type);
                if ($domain === null) {
                    return [];
                }
                $domains[] = $domain;
            }
        }
        if ($markers === [] || $domains === []) {
            return [];
        }
        $settings = new Settings($operation->statementInformation()->connection ?? Settings::defaults($operation->profile()->grammar)->connection);
        $context = new \SqlSemantics\Contract\AnalysisContext($operation->profile(), [new \SqlSemantics\Statement\Identifier\Name('intrinsic')], session: $settings);
        $domain = (new Aggregation(new Collations($settings->connection)))->of($domains, 'case', new Derivation($context));
        if ($domain === null) {
            return [];
        }
        $inferred = match ($domain->kind) {
            Kind::Null => Domain::string(65535, Collation::binary(), coercibility: Coercibility::Coercible),
            Kind::String => Domain::string(intdiv(65535, $domain->collation->charset->maxLength), $domain->collation, coercibility: Coercibility::Coercible),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit => ParameterInference::operand($domain, false),
        };

        return array_fill_keys($markers, $inferred);
    }
}
