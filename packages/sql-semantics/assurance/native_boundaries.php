<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

function nativeBoundaryContract(string $dialect): array
{
    $records = match ($dialect) {
        'sqlite' => [
            ['sqlite-rename-boundaries-v1.json', '31296dc3a1320f0e40dd162e066cc4df0c90a47f2e7cc21b2d0fad4a5b40da5f'],
            ['sqlite-conflict-boundaries-v3.json', 'e213c39415e301abdf7684710d923ac82b30de9e2ce9b0e3ae361e5297291e84'],
            ['sqlite-query-scope-boundaries-v9.json', 'bda4f3f5e8f81d04f57629a4f47e81cb2ba2f8a5b2761ffaceb7e42692f68006'],
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v4.json', '299d27d56b6190fbb5bc9e1c89704579155cdb1bf083b0bae5aa230577911c00'],
            ['sqlite-insert-slots-boundaries-v5.json', '55d984c88686949361c74660945cc67450c02a86b6a69c53aec6f079f46f87c0'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v17.json', '4c6b56c3f8634f50f7130e02ed496c6955da7460c32e6290f569ff2ccb74787a'],
            ['sqlite-default-boundaries-v10.json', 'c8f8df9a7f46f80a597729373e56e103d17fb025edc4213be5c4a51fd932725a'],
            ['sqlite-drop-view-boundaries-v1.json', '565bcb84c952123e8e1c311606be91b0f0e7a04e63833aa025ea883852df1f02'],
            ['sqlite-create-view-boundaries-v4.json', '98fe27cbdbf1c468c1c633c8449ad89002257aaa2826fd1e9af60f114f11caaa'],
            ['sqlite-mutation-returning-boundaries-v3.json', 'bf2ac57e17e98eeedb1b078271a5f02c7daef17aa3c33339af6e7909d64d350b'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-mutation-returning-boundaries-v2.json', '082d7dd3af450a824ec217de545754e2f034eb33027dbb342456531b31dcefc3'],
            ] : []),
            ['native-boundaries-v31.json', '3fca83f52f260bf9f43b909228eeed7f00d77534d2183368b530673364738b55'],
            ['native-insert-query-boundaries-v4.json', '67796e3b509a778308dcdf70c04ebcf7d455e9014faac77c426f6ee105290373'],
        ],
    };
    $records[] = ['identifier-boundaries-v1.json', 'e526dfe9c9f107bf37e8aa1a38a006e7615f9dcf912d2087d564c19b500c7ab0'];
    $rules = [];
    foreach ($records as [$name, $digest]) {
        $path = __DIR__ . '/' . $name;
        requireShape(is_file($path) && hash_file('sha256', $path) === $digest, "Boundary contract differs from its recorded revision: $name");
        $additional = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape($additional !== [] && array_intersect_key($additional, $rules) === [], "Boundary contract has no distinct rules: $name");
        $rules += $additional;
    }

    $amendments = match ($dialect) {
        'mysql' => [
            ['mysql-from-list-boundaries-v1.json', '75520d651cdde8992dcf05de45be54b9cb5d44d3af3f3536b612d6e83d7a443d'],
            ['mysql-text-literal-boundaries-v1.json', '46a77328b7d16609bd966a6ac2c1086a7d62e708606f0c7774d22d2a756fdb12'],
            ['mysql-varchar-slot-boundaries-v1.json', '07b2a4d8f69b29a9739f3afceaffcb2fe676c2bb64ceef286fd7c3f4df0bc03d'],
            ['mysql-varchar-presence-boundaries-v1.json', 'fffbc606bf20d51b751ac695b96badb2b64a4ee91c0e3ab9338afc0973240045'],
            ['mysql-varchar-label-boundaries-v1.json', 'ddfd1e58cc81339e59694a5f7250d46c10b482c1d37f4a201a8bbd17835c2fe9'],
            ['mysql-varchar-comparison-boundaries-v1.json', '8d997095c80064880f6c6e3ef6e8c43c90ea4490a909eeb5ef64ce545963b149'],
            ['mysql-varchar-assignment-boundaries-v1.json', '17bf1801f1d93fc7a69ae05645249e9d4483c0cfc0b62240cb3aa278e586714a'],
            ['mysql-varchar-insert-boundaries-v1.json', 'cb98b50df5693008bd453adbf33981fc46f465162deede996663ad8f25a4bf2d'],
            ['mysql-varchar-insert-query-boundaries-v1.json', '9c115dd302a146cc12a56b8a268e3dba385a2c27a2fed8e366c37af0b9a1fd7a'],
            ['mysql-varchar-count-boundaries-v1.json', 'dd89e17397c9f9e6abe9f73b9a4710b1bc91eb09561c035ac973d860552a45bc'],
            ['mysql-varchar-extrema-boundaries-v1.json', 'c2c47d6e7d9a220b5ffc43c3e9c9b13bbcea824736c17d10e729eedc46754cf0'],
            ['mysql-explicit-text-collation-boundaries-v1.json', '6bfb85c25b4dbd401b4ede0577d7fcd8031bbb07c835064552a1525e7d772a6d'],
            ['mysql-derived-varchar-boundaries-v1.json', 'bed891c263a93d0c62cbcfec908fa171a593d6a55fec81020856a2d94b9b97d7'],
            ['mysql-create-varchar-boundaries-v1.json', 'cca62d6472498414b159b7934303e1250ddcda6103dc2a4f25460dee572b741d'],
            ['mysql-create-signed-boundaries-v1.json', 'daff522af411c8dac52a3d7d14c477483842c67b572683ea3b7e2bc5274693cf'],
            ['mysql-create-primary-boundaries-v2.json', '1657633bf073bbb6ff22462e5d1fc29e23de126d074eec5f3d6e56c506e384a3'],
        ],
        'postgres' => [
            ['postgres-create-text-boundaries-v1.json', 'f2a783062d780bf0fc95fdf27394c04238c992e524c58c3c19b02885538de98b'],
            ['postgres-create-signed-defaults-boundaries-v1.json', '40f1569f5715ae2b1addfb7927e4b6322a965ca877e65a6c37f85816a6b5961d'],
            ['postgres-create-boolean-boundaries-v1.json', 'ce3ed3ed39b2cb747ddf8ae50e3f27e4e093b78c20c4ef2e66e8610f090598b9'],
            ['postgres-query-boundaries-v9.json', '43a22d2bc34a130a0dc8e70842f9b1a04027c28284c2a459dde1c1dc9891cd95'],
            ['postgres-derived-text-boundaries-v1.json', '93ca7467eac18a2c3b8b29b19d6010e524b4dea52bcb48ff8e43e316e1cd30b6'],
            ['postgres-text-returning-boundaries-v1.json', '3cf2b6db5f68514d6ef1f34dfa3bb34746169fad138f7ac478f1214d4ace3565'],
            ['postgres-text-update-boundaries-v2.json', 'e38cfe97d78a85516a6175bca7965bfd06f22ffd7953c6f636d49cc910ab8b07'],
            ['postgres-text-insert-boundaries-v2.json', '8c2787a86847cf6b6e8db819769588e6dc94a5f3d2ed4dc176f00e97e75865cf'],
            ['postgres-text-insert-query-boundaries-v3.json', '99fe1cb60a230a76d15cb58a7c50bec6cc851d90188286d790e5e3ee6fdfac0b'],
            ['postgres-collated-text-boundaries-v1.json', '09835aa776c76916d1f6375f9fa44ad52dbe129a3c847ffb1a7f8ba334189995'],
            ['postgres-text-comparison-boundaries-v2.json', '3e7d4b2c945608b48c52a541b2cb85b474f4097a67475b489725d300aaed6114'],
            ['postgres-select-bindings-boundaries-v1.json', '9034762ce0de61e958cbba1eb314bdbde112920bb172cd0e87190f059dc4fc79'],
            ['postgres-boolean-fields-boundaries-v1.json', 'f4d06df0e8bfcca315a114851209270de7e19543bcc3d5b4ed8c9760db31545e'],
            ['postgres-boolean-returning-boundaries-v1.json', '37752e21d0c6ade880a6af530b1238d5ce335e5fcc663b3b4e1a378a5da560bb'],
            ['postgres-boolean-writes-boundaries-v1.json', 'b72bfce51bcb67617dbdb29eca44c831bd80d663bd7327f551b4b4345387c4b2'],
            ['postgres-boolean-write-inputs-boundaries-v1.json', '4d8fcc71a9c381dc6c3f10a9bda953ae8b799395e8b92d93ad713110a06ad683'],
            ['postgres-int8-fields-boundaries-v1.json', 'b3ca90d0760af06e4c1d5f4e23ccae910469a0a67800a5cd97bed38d591e5056'],
            ['postgres-output-order-names-boundaries-v1.json', '187c056bcc5b57ed10024097f97f397ef449c75149c248b1a46b4a891a97aacf'],
        ],
        'sqlite' => [],
    };
    foreach ($amendments as [$name, $digest]) {
        $path = __DIR__ . '/' . $name;
        requireShape(hash_file('sha256', $path) === $digest, "Amended boundary contract differs from its recorded revision: $name");
        $amendment = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $base = [$amendment['base']['path'], $amendment['base']['sha256']];
        requireShape(in_array($base, $records, true), "Amended boundary contract base is not active: $name");
        foreach ($amendment['rules'] as $rule) {
            requireShape($rule['dialects'] === [$dialect], "Amended boundary contract changed its dialect: $name");
        }
        $rules = array_replace($rules, $amendment['rules']);
    }

    return array_filter($rules, static fn (array $rule): bool => !isset($rule['dialects']) || in_array($dialect, $rule['dialects'], true));
}

function checkNativeBoundaries(
    ClassLike $class,
    string $role,
    string $platform,
    string $dialect,
    array $rules,
    Parser $parser,
    NodeTraverser $traverser,
    Standard $printer,
): array {
    $imports = '';
    foreach (['Model\\Diagnostic\\Check', 'Model\\Diagnostic\\InvalidConstruction', 'Ir\\Identifier', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Rows\\LocalSource', 'Ir\\Rows\\ValuesQuery', 'Ir\\Mutation\\InsertConflict', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\Ordering\\Order', 'Ir\\Ordering\\ResultSql', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate',  'Statement\\Expression\\Grouped', 'Statement\\Query\\SelectExpression', 'Ir\\ExpressionLowering',
        'Ir\\Binding\\ParameterLowering', 'Ir\\Ordering\\ProjectionLabel'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
    if ($dialect === 'sqlite') {
        $imports .= 'use ' . $platform . '\\Ir\\From\\SelectBounds;' . "\n";
        $imports .= 'use ' . $platform . '\\Ir\\Rows\\RecursiveDefinition;' . "\n";
        $imports .= 'use ' . $platform . '\\Platform;' . "\n";
        foreach (['Ir\\SourceBinding', 'Ir\\From\\QueryClauses', 'Ir\\Mutation\\InsertConflictHandler', 'Ir\\Mutation\\InsertTargetLowering', 'Ir\\Mutation\\ReturningLowering',
            'Statement\\Mutation\\InsertDefaults', 'Statement\\Mutation\\Assignment', 'Statement\\Mutation\\ConflictResolution',
            'Ir\\Predicate\\Pattern\\PatternTest', 'Ir\\Predicate\\Pattern\\PatternKind', 'Statement\\Expression\\Operator\\PatternOperator',
            'Statement\\Expression\\Operator\\PatternMatch as ParsedPattern'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        foreach (['Ir\\Parameter', 'Ir\\Collation', 'Ir\\Expression\\BinaryOperatorLowering', 'Ir\\ArithmeticOperator', 'Statement\\Expression\\Operator\\BinaryOperator', 'Ir\\Predicate\\ComparisonOperator', 'Statement\\Expression\\Operator\\NullTestForm', 'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\BetweenTest', 'Ir\\Predicate\\MembershipTest', 'Ir\\Predicate\\MembershipItem', 'Ir\\Predicate\\FoldedMembership', 'Ir\\Predicate\\FoldedMembershipLowering', 'Model\\Rendering\\Output', 'Model\\Rendering\\PieceKind', 'Rendering\\Codec', 'Statement\\Expression\\TruthWord',
            'Statement\\Expression\\FunctionCall', 'Statement\\Expression\\Operator\\Binary', 'Ir\\Expression\\CaseExpression', 'Ir\\Expression\\CaseBranch', 'Ir\\Expression\\CastExpression', 'Ir\\Expression\\CollatedExpression', 'Ir\\Expression\\Concatenation', 'Statement\\Expression\\Collate as ParsedCollate', 'Ir\\Expression\\CastTarget', 'Ir\\Expression\\UnaryExpression', 'Ir\\Expression\\UnaryKind',
            'Ir\\Literal\\NumericLiteralLowering', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
            'Statement\\Expression\\Literal\\IntegerLiteral as ParsedInteger', 'Statement\\Expression\\Literal\\HexLiteral as ParsedHex',
            'Statement\\Expression\\Literal\\RealLiteral as ParsedReal', 'Statement\\Type\\Affinity', 'Ir\\Literal\\BlobLiteral',
            'Ir\\Literal\\HexIntegerLiteral', 'Ir\\Literal\\RealLiteral', 'Ir\\Literal\\TextLiteral', 'Ir\\Predicate\\Comparison', 'Ir\\Predicate\\Logical', 'Ir\\Predicate\\ExpressionScope', 'Ir\\Schema\\TableColumn',
            'Ir\\Schema\\DeclaredDatatypeName', 'Ir\\Schema\\NotNullConflict', 'Ir\\Schema\\DefaultLowering', 'Ir\\Mutation\\ReturnedColumn', 'Ir\\Mutation\\DefaultRow',
            'Ir\\Mutation\\UpdateConflict', 'Statement\\Expression\\ColumnUse', 'Statement\\Expression\\DoubleQuotedWord', 'Statement\\Query\\TableStar', 'Ir\\From\\SourceDeclarations', 'Statement\\Schema\\Column\\NotNull',
            'Statement\\Schema\\Column\\DefaultLiteral', 'Statement\\Schema\\Column\\DefaultExpression', 'Statement\\Schema\\Column\\DefaultWord', 'Statement\\Schema\\Table\\TableOptionKind', 'Statement\\Mutation\\MutationTarget',
            'Ir\\Schema\\Alter\\RenameColumn', 'Ir\\Schema\\Alter\\RenameTable',
            'Model\\Statement\\Declaration\\RelationKind', 'Model\\Statement\\Reference\\Table\\DeclaredTable',
            'Statement\\Schema\\AlterRenameColumn', 'Statement\\Schema\\AlterRenameTable',
            'Ir\\Schema\\CreateTable', 'Ir\\Schema\\Key\\KeyConflict', 'Ir\\Schema\\Key\\KeyTerm', 'Ir\\Schema\\Key\\PrimaryKey',
            'Ir\\Schema\\Key\\KeyTermLowering', 'Ir\\Schema\\Key\\TableKeySql', 'Ir\\Schema\\Key\\TableKeyLowering',
            'Ir\\Schema\\Key\\TableKeyLayout', 'Ir\\Schema\\Key\\UniqueKey',
            'Statement\\Schema\\LiteralColumn', 'Statement\\Query\\Ordering\\SortDirection',
            'Statement\\Schema\\Column\\ColumnPrimaryKey', 'Statement\\Schema\\Table\\TablePrimaryKey',
            'Statement\\Schema\\Column\\ColumnUnique', 'Statement\\Schema\\Table\\TableUnique',
            'Statement\\Schema\\Column\\Collation as ParsedCollation',
            'Ir\\Schema\\Predicate\\CheckConstraint', 'Ir\\Schema\\Predicate\\CheckLowering', 'Ir\\Schema\\Predicate\\ImplicitCheckLabel', 'Ir\\Schema\\Predicate\\ComparisonSpelling', 'Ir\\Schema\\Predicate\\ImplicitCheckLowering', 'Ir\\Schema\\Predicate\\DefinitionSource', 'Ir\\Schema\\TableDeclarationSql', 'Statement\\Schema\\Column\\ColumnCheck', 'Statement\\Schema\\Column\\ConstraintName', 'Statement\\Schema\\Table\\TableCheck',
            'Ir\\Schema\\Generation\\GeneratedGraph', 'Ir\\Schema\\Generation\\GeneratedLowering', 'Ir\\Schema\\Generation\\ColumnGeneration', 'Ir\\Schema\\Generation\\Storage',
            'Ir\\Rows\\LocalInputs', 'Ir\\Rows\\LocalSourceLowering', 'Ir\\From\\ExternalRelationKind',
            'Ir\\Expression\\Nulls\\Coalesce', 'Ir\\Expression\\Nulls\\CoalesceArgument', 'Ir\\Expression\\Nulls\\CoalesceLowering',
            'Statement\\Expression\\Operator\\NullTest as OperatorNullTest', 'Statement\\Expression\\Operator\\Between as ParsedBetween',
            'Statement\\Expression\\Subquery\\InList as ParsedMembership', 'Statement\\Expression\\CaseExpression as ParsedCase',
            'Statement\\Expression\\Cast as ParsedCast', 'Statement\\Expression\\Literal\\TextLiteral as ParsedText',
            'Statement\\Expression\\Literal\\BlobLiteral as ParsedBlob', 'Statement\\Expression\\Literal\\NullLiteral as ParsedNull',
            'Statement\\Expression\\BindParameter', 'Statement\\Expression\\ParameterPrefix',
            'Statement\\Relation\\JoinOn', 'Statement\\Relation\\JoinChain', 'Statement\\Relation\\JoinUsing', 'Statement\\Relation\\JoinStep',
            'Statement\\Relation\\JoinKeyword', 'Ir\\From\\JoinKeyLowering', 'Model\\Statement\\Declaration\\Column', 'Model\\Statement\\Identifier\\Name', 'Statement\\Query\\SetQuantifier', 'Statement\\Query\\With\\WithQuery',
            'Statement\\Query\\Compound', 'Model\\Statement\\Reference\\Table\\CommonTable',
            'Statement\\Relation\\TableInput', 'Model\\Statement\\Declaration\\Table',
                        'Ir\\Schema\\DropTable', 'Ir\\Schema\\DropView', 'Statement\\Schema\\AbsentRelation', 'Statement\\Schema\\SchemaObjectKind',
            'Ir\\Schema\\DeclaredDatatypeLowering', 'Statement\\Schema\\Column\\Generated', 'Statement\\Schema\\Column\\GeneratedStorage', 'Statement\\Type\\ColumnDomain',
            'Ir\\Schema\\CreateSchema', 'Ir\\Schema\\Query\\CreateTableAs', 'Ir\\Schema\\Query\\CreateAsAffinity',
            'Ir\\Schema\\Query\\CreateAsLayout', 'Ir\\Schema\\Query\\CreatedColumn', 'Ir\\Schema\\Query\\CreatedColumnDeclaration',
            'Ir\\Aggregation\\GroupedSelect', 'Ir\\Aggregation\\GroupedLowering', 'Ir\\Rows\\Compound as CompoundQuery',
            'Ir\\Rows\\CompoundLowering', 'Ir\\Rows\\CompoundBounds', 'Statement\\Query\\Compound as ParsedCompound',
            'Ir\\Schema\\View\\CompoundViewBounds', 'Ir\\Schema\\View\\ViewSql',
            'Ir\\Schema\\View\\ViewQueryBounds', 'Ir\\Schema\\View\\CreateView', 'Statement\\Schema\\CreateView as ParsedCreateView',
            'Ir\\Rows\\ValuesLowering', 'Statement\\Query\\ValuesClause'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\SelectBounds;' . "\n";
        if ($dialect === 'postgres') {
            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
            foreach (['Statement\\Constraint\\Column\\DefaultExpression', 'Statement\\Constraint\\Column\\NotNull', 'Statement\\Constraint\\Column\\NullAllowed',
                'Statement\\Table\\Element\\ColumnDefinition', 'Statement\\Table\\Element\\ListedColumns', 'Statement\\Table\\Persistence',
                'Ir\\Expression\\IntegerCase', 'Ir\\Expression\\IntegerCaseArm', 'Ir\\Expression\\IntegerCaseLowering',
                'Ir\\ColumnReference as IrColumnReference', 'Ir\\From\\BooleanColumnReference', 'Ir\\From\\WideIntegerColumnReference', 'Ir\\Expression\\BooleanInput', 'Ir\\From\\ProjectionLowering', 'Model\\Statement\\Reference\\Column\\AliasTarget', 'Statement\\Query\\SortItem', 'Statement\\Query\\SortDirection', 'Statement\\Query\\NullsOrder', 'Ir\\From\\ColumnKind', 'Ir\\From\\TextColumnReference', 'Statement\\Manipulation\\TargetTable', 'Ir\\Expression\\BuiltinCast', 'Ir\\Expression\\BuiltinCastLowering', 'Ir\\Expression\\CastDestination', 'Ir\\Expression\\TextLiteral', 'Ir\\Expression\\WideIntegerLiteral', 'Ir\\Binding\\ParameterSignature', 'Ir\\From\\JoinBinding',
                'Ir\\Expression\\CollatedText', 'Ir\\Expression\\CollatedTextLowering', 'Ir\\Expression\\TextCollation', 'Statement\\Expression\\Collation',
                'Ir\\Predicate\\Text\\TextComparison', 'Ir\\Predicate\\Text\\TextComparisonLowering', 'Ir\\Predicate\\Text\\TextOperator',
                'Ir\\Predicate\\IntegerRange', 'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\IntegerAtomLowering',
                'Ir\\Predicate\\IntegerDistinctness', 'Ir\\Predicate\\IntegerDistinctnessLowering',
                'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\TruthOperator', 'Ir\\Predicate\\TruthTestLowering',
                'Statement\\Expression\\Predicate\\BooleanTest as ParsedTruthTest',
                'Statement\\Expression\\BinaryOperation', 'Statement\\Expression\\ColumnReference as ParsedColumn',
                'Statement\\Expression\\Operator\\Between as ParsedRange', 'Statement\\Expression\\Operator\\DistinctTest as ParsedDistinctness',
                'Statement\\Expression\\Operator\\UnaryOperation',
                'Statement\\Expression\\Constructor\\CaseExpression as ParsedCase', 'Statement\\Literal\\BooleanLiteral as ParsedBoolean',
                'Statement\\Literal\\Constant', 'Statement\\Literal\\IntegerConstant', 'Statement\\Literal\\NumericConstant', 'Statement\\Literal\\StringConstant', 'Ir\\Predicate\\PredicateLowering',
                'Ir\\From\\MergedJoinLowering', 'Statement\\Relation\\JoinKind as ParsedKind', 'Statement\\Relation\\JoinOn', 'Statement\\Relation\\JoinUsing',
                'Statement\\Relation\\RelationList', 'Statement\\Relation\\ParenthesizedJoin', 'Statement\\Relation\\TableInput', 'Model\\Statement\\Identifier\\Name', 'Ir\\SourceBinding', 'Ir\\Mutation\\ReturningSql', 'Ir\\Mutation\\ReturningLayout', 'Ir\\Mutation\\ReturningLowering',
                'Ir\\Mutation\\Insert\\Destination', 'Ir\\Mutation\\Insert\\WriteSlot', 'Ir\\Mutation\\InsertTargetBinding', 'Ir\\Mutation\\InsertCell',
                'Ir\\Mutation\\ReturnedColumn', 'Model\\Contract\\ParameterStyle', 'Statement\\Manipulation\\CurrentOf',
                'Statement\\Manipulation\\Assignment\\Assignment as ParsedAssignment',
                'Statement\\Expression\\ColumnReference as ParsedReturnColumn',
                'Statement\\Expression\\Cast', 'Statement\\Literal\\NullLiteral as ParsedNull', 'Statement\\Expression\\BinaryOperation as ParsedComparison',
                'Statement\\Expression\\Operator\\BooleanOperation as ParsedLogical', 'Statement\\Expression\\Operator\\BooleanOperator',
                'Statement\\Expression\\Operator\\Negation as ParsedNot', 'Statement\\Expression\\Predicate\\NullTest as ParsedNullTest',
                'Model\\Statement\\Type\\Known', 'Statement\\Type\\Descriptor\\Builtin', 'Ir\\Rows\\Compound', 'Ir\\Rows\\CompoundOperator', 'Ir\\Rows\\CompoundShape', 'Ir\\Ordering\\ResultLowering', 'Statement\\Literal\\PositionalParameter', 'Statement\\Query\\SetOperation', 'Statement\\Query\\SetQuantifier', 'Ir\\Rows\\ResultKind'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
        if ($dialect === 'mysql') {
            foreach (['Ir\\ArithmeticOperator', 'Ir\\Literal\\IntegerTokenLowering', 'Ir\\Predicate\\IntegerRange',
                'Ir\\Literal\\TextLiteral', 'Ir\\Literal\\TextLiteralLowering', 'Statement\\Literal\\EscapeRule', 'Statement\\Literal\\StringLiteral',
                'Ir\\From\\ColumnKind', 'Ir\\From\\Varchar', 'Ir\\From\\TextCollation', 'Ir\\From\\TextColumnReference',
                'Ir\\From\\VarcharLowering', 'Statement\\Type\\Character', 'Statement\\Type\\Kind\\CharacterKind',
                'Ir\\Predicate\\Text\\TextComparison', 'Ir\\Predicate\\Text\\TextOperator', 'Ir\\Predicate\\Text\\TextComparisonLowering',
                'Ir\\Expression\\CollatedText', 'Ir\\Expression\\CollatedTextLowering', 'Statement\\Expression\\Operator\\Collated',
                'Ir\\Mutation\\TextUpdateAssignment', 'Ir\\Mutation\\UpdateAssignmentLowering', 'Ir\\Mutation\\Update',
                'Ir\\Mutation\\InsertCell', 'Ir\\Mutation\\Insert', 'Ir\\Mutation\\InsertTargetBinding',
                'Model\\Statement\\Type\\Nullability', 'Model\\Contract\\ParameterStyle', 'Model\\Contract\\LexicalSettings',
                'Statement\\Type\\Integral', 'Statement\\Type\\Kind\\IntegralKind',
                'Statement\\Table\\Column\\CollateAttribute', 'Statement\\Table\\Column\\DefaultLiteral', 'Statement\\Table\\Column\\KeywordAttribute',
                'Statement\\Table\\Column\\Kind\\ColumnKeyword', 'Statement\\Table\\Column\\OrdinaryColumn', 'Statement\\Table\\Column\\ColumnDefinition',
                'Statement\\Table\\Option\\EngineOption', 'Statement\\Table\\Option\\CharsetOption', 'Statement\\Table\\Option\\CollationOption',
                'Rules\\TableDefinition\\TableDeclaration', 'Rules\\SessionDatabase',
                'Statement\\Query\\Direction', 'Statement\\Table\\Key\\ColumnPart', 'Statement\\Table\\Key\\IndexDefinition',
                'Statement\\Table\\Key\\IndexKind', 'Rules\\TableDefinition\\ColumnFlags',
                'Ir\\Schema\\Key\\PrimaryKey', 'Ir\\Schema\\Key\\PrimaryKeyColumn', 'Ir\\Schema\\Key\\CreatePrimaryKeyLowering', 'Ir\\Schema\\Key\\PrimaryColumnPolicy',
                'Ir\\Expression\\SignedCast', 'Ir\\Expression\\UnsignedCast', 'Ir\\Expression\\IntegralCastLowering',
                'Statement\\Literal\\Parameter as ParsedParameter',
                'Statement\\Expression\\Conversion\\Cast', 'Statement\\Type\\Kind\\CastKind',
                'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\RangeIntegerLiteral',
                'Ir\\From\\MergedJoinLowering', 'Ir\\SourceBinding', 'Ir\\Predicate\\PredicateLowering', 'Statement\\Relation\\Join\\JoinOperator', 'Statement\\Relation\\TableList', 'Model\\Statement\\Identifier\\Name',
                'Statement\\Expression\\Operator\\Arithmetic as ParsedArithmetic', 'Statement\\Expression\\Predicate\\Between as ParsedRange',
                'Statement\\Literal\\BooleanLiteral as ParsedBoolean', 'Statement\\Literal\\NullLiteral as ParsedNull',
                'Statement\\Name\\ColumnUse', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
                'Statement\\Literal\\NumberForm', 'Statement\\Literal\\NumberLiteral','Statement\\Literal\\SignedLiteral',
                'Statement\\Expression\\Comparison as ParsedComparison', 'Statement\\Expression\\Logical as ParsedLogical',
                'Statement\\Expression\\Not as ParsedNot', 'Statement\\Expression\\NullTest as ParsedNullTest'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
        $imports .= 'use ' . $platform . '\\Ir\\Binding\\Parameter;' . "\n";
    }
    $imports .= 'use ' . $platform . '\\Model\\Statement\\Identifier\\Comparison as NameComparison;' . "\n";
    $imports .= 'use ' . $platform . '\\Statement\\Query\\Select as ParsedSelect;' . "\n";
    $imports .= 'use ' . $platform . '\\Statement\\Relation\\' . ($dialect === 'mysql' ? 'JoinedTable' : 'Join') . ' as ParsedJoin;' . "\n";
    $verified = [];
    foreach ($rules as $id => $rule) {
        [$type, $method] = explode('::', $id);
        if ($type !== $role) {
            continue;
        }
        $code = implode("\n", [...$rule['common'], ...($rule[$dialect] ?? []), ...($rule['tail'] ?? [])]);
        $parts = explode('/', $role);
        array_pop($parts);
        $namespace = implode('\\', [$platform, 'Ir', ...$parts]);
        $template = $traverser->traverse($parser->parse('<?php namespace ' . $namespace . ';' . $imports . $code));
        requireShape(count($template) === 1 && $template[0] instanceof Namespace_, "Invalid native boundary template $id");
        $statements = array_values(array_filter($template[0]->stmts, static fn ($node) => !$node instanceof Use_));
        $actual = $class->getMethod($method)?->stmts;
        requireShape($actual !== null && $printer->prettyPrint($actual) === $printer->prettyPrint($statements), "Native boundary differs in $id");
        $verified[] = $id;
    }

    return $verified;
}
