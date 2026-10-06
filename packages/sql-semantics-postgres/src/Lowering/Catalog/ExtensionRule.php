<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\AlterExtensionContents;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\CreateExtension;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionVersion;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\UpdateExtension;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateAccessMethod;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguageExtension;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the extension, language and access method commands.
 *
 * Rule: PG-EXTENSION-LOWER-001. Scope: `CreateExtensionStmt`,
 * `create_extension_opt_list`, `create_extension_opt_item`,
 * `AlterExtensionStmt`, `alter_extension_opt_list`, `alter_extension_opt_item`,
 * `AlterExtensionContentsStmt`, `CreatePLangStmt`, `handler_name`,
 * `opt_inline_handler`, `validator_clause`, `opt_validator`, `CreateAmStmt`,
 * `am_type`. Constructors: `CreateExtension`, `ExtensionSchema`,
 * `ExtensionVersion`, `UpdateExtension`, `AlterExtensionContents`,
 * `CreateLanguage`, `CreateLanguageExtension`, `FunctionClause`,
 * `CreateAccessMethod`. The objects of ALTER EXTENSION use the object
 * references of the routine family. Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html, https://www.postgresql.org/docs/17/sql-alterextension.html,
 * https://www.postgresql.org/docs/17/sql-createlanguage.html, https://www.postgresql.org/docs/17/sql-create-access-method.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class ExtensionRule
{
    /**
     * The object kind of each ALTER EXTENSION production that spells its kind with keywords.
     */
    private const KINDS = [
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop AGGREGATE aggregate_with_argtypes' => ObjectKind::Aggregate,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop CAST ( Typename AS Typename )' => ObjectKind::Cast,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop DOMAIN_P Typename' => ObjectKind::Domain,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop FUNCTION function_with_argtypes' => ObjectKind::Function,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR operator_with_argtypes' => ObjectKind::Operator,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR CLASS any_name USING name' => ObjectKind::OperatorClass,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR FAMILY any_name USING name' => ObjectKind::OperatorFamily,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop PROCEDURE function_with_argtypes' => ObjectKind::Procedure,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop ROUTINE function_with_argtypes' => ObjectKind::Routine,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop TRANSFORM FOR Typename LANGUAGE name' => ObjectKind::Transform,
        'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop TYPE_P Typename' => ObjectKind::Type,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an extension, language or access method command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        if ($statement->name === 'AlterExtensionContentsStmt') {
            return $this->contents($form);
        }

        return match ($form->signature) {
            'CreateExtensionStmt: CREATE EXTENSION name opt_with create_extension_opt_list' => new CreateExtension($names->name($form->node(2)), false, $this->options($form->node(4))),
            'CreateExtensionStmt: CREATE EXTENSION IF_P NOT EXISTS name opt_with create_extension_opt_list' => new CreateExtension($names->name($form->node(5)), true, $this->options($form->node(7))),
            'AlterExtensionStmt: ALTER EXTENSION name UPDATE alter_extension_opt_list' => new UpdateExtension($names->name($form->node(2)), $this->versions($form->node(4))),
            'CreatePLangStmt: CREATE opt_or_replace opt_trusted opt_procedural LANGUAGE name' => new CreateLanguageExtension($this->lowering->flags->present($form->node(1)), (new CatalogFlags($this->lowering))->present($form->node(2)), $names->name($form->node(5))),
            'CreatePLangStmt: CREATE opt_or_replace opt_trusted opt_procedural LANGUAGE name HANDLER handler_name opt_inline_handler opt_validator' => new CreateLanguage(
                $this->lowering->flags->present($form->node(1)),
                (new CatalogFlags($this->lowering))->present($form->node(2)),
                $names->name($form->node(5)),
                $this->handler($form->node(7)),
                $this->inline($form->node(8)),
                $this->validator($form->node(9)),
            ),
            'CreateAmStmt: CREATE ACCESS METHOD name TYPE_P am_type HANDLER handler_name' => new CreateAccessMethod($names->name($form->node(3)), $this->accessMethodKind($form->node(5)), $this->handler($form->node(7))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `AlterExtensionContentsStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function contents(Form $form): AlterExtensionContents
    {
        $extension = $this->lowering->names->name($form->node(2));
        $action = $this->lowering->flags->addOrDrop($form->node(3));
        $routines = $this->lowering->routines;
        $types = $this->lowering->types;
        if ($form->signature === 'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop object_type_name name') {
            return new AlterExtensionContents($extension, $action, $routines->objectKind($form->node(4)), new UnqualifiedName($this->lowering->names->name($form->node(5))));
        }
        if ($form->signature === 'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop object_type_any_name any_name') {
            return new AlterExtensionContents($extension, $action, $routines->objectKind($form->node(4)), $this->lowering->names->dotted($form->node(5)));
        }
        $kind = self::KINDS[$form->signature] ?? throw ImplementationGap::production($form);
        $object = match ($form->signature) {
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop AGGREGATE aggregate_with_argtypes' => $routines->aggregateSignature($form->node(5)),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop CAST ( Typename AS Typename )' => new CastPair($types->typeName($form->node(6)), $types->typeName($form->node(8))),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop DOMAIN_P Typename',
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop TYPE_P Typename' => new TypeReference($types->typeName($form->node(5))),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR operator_with_argtypes' => $routines->operatorSignature($form->node(5)),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR CLASS any_name USING name',
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop OPERATOR FAMILY any_name USING name' => new OperatorGroupName($this->lowering->names->dotted($form->node(6)), $this->lowering->names->name($form->node(8))),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop TRANSFORM FOR Typename LANGUAGE name' => new TransformFor($types->typeName($form->node(6)), $this->lowering->names->name($form->node(8))),
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop FUNCTION function_with_argtypes',
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop PROCEDURE function_with_argtypes',
            'AlterExtensionContentsStmt: ALTER EXTENSION name add_drop ROUTINE function_with_argtypes' => $routines->functionSignature($form->node(5)),
        };

        return new AlterExtensionContents($extension, $action, $kind, $object);
    }

    /**
     * Lowers `create_extension_opt_list`.
     *
     * @return list<ExtensionOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'create_extension_opt_list: create_extension_opt_list create_extension_opt_item', 'create_extension_opt_list:') as $item) {
            $form = $this->lowering->productions->form($item);
            $options[] = match ($form->signature) {
                'create_extension_opt_item: SCHEMA name' => new ExtensionSchema($this->lowering->names->name($form->node(1))),
                'create_extension_opt_item: VERSION_P NonReservedWord_or_Sconst' => new ExtensionVersion(VersionRole::Target, $this->lowering->options->wordOrString($form->node(1))),
                'create_extension_opt_item: FROM NonReservedWord_or_Sconst' => new ExtensionVersion(VersionRole::Source, $this->lowering->options->wordOrString($form->node(1))),
                'create_extension_opt_item: CASCADE' => ExtensionCascade::Cascade,
                default => throw ImplementationGap::production($form),
            };
        }

        return $options;
    }

    /**
     * Lowers `alter_extension_opt_list`: the versions written after TO.
     *
     * @return list<Word|StringConstant>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function versions(Node $list): array
    {
        $versions = [];
        foreach ($this->lowering->items($list, 'alter_extension_opt_list: alter_extension_opt_list alter_extension_opt_item', 'alter_extension_opt_list:') as $item) {
            $form = $this->lowering->productions->form($item);
            $versions[] = $form->signature === 'alter_extension_opt_item: TO NonReservedWord_or_Sconst' ? $this->lowering->options->wordOrString($form->node(1)) : throw ImplementationGap::production($form);
        }

        return $versions;
    }

    /**
     * Lowers `handler_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function handler(Node $name): DottedName
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'handler_name: name' => new DottedName([$this->lowering->names->name($form->node(0))]),
            'handler_name: name attrs' => new DottedName([$this->lowering->names->name($form->node(0)), ...$this->lowering->names->attributes($form->node(1))]),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_inline_handler`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function inline(Node $handler): ?DottedName
    {
        $form = $this->lowering->productions->form($handler);

        return match ($form->signature) {
            'opt_inline_handler: INLINE_P handler_name' => $this->handler($form->node(1)),
            'opt_inline_handler:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_validator` or `validator_clause`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function validator(Node $validator): ?FunctionClause
    {
        $form = $this->lowering->productions->form($validator);

        return match ($form->signature) {
            'opt_validator: validator_clause' => $this->validator($form->node(0)),
            'opt_validator:' => null,
            'validator_clause: VALIDATOR handler_name' => new FunctionClause(FunctionRole::Validator, $this->handler($form->node(1))),
            'validator_clause: NO VALIDATOR' => new FunctionClause(FunctionRole::Validator),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `am_type`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function accessMethodKind(Node $kind): AccessMethodKind
    {
        $form = $this->lowering->productions->form($kind);

        return match ($form->signature) {
            'am_type: INDEX' => AccessMethodKind::Index,
            'am_type: TABLE' => AccessMethodKind::Table,
            default => throw ImplementationGap::production($form),
        };
    }
}
