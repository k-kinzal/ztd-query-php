"""Compare native MySQL creation from a checked projection with an independent effect model."""

from copy import deepcopy
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_session_create_table import COLLATION, DIAGNOSTIC, OLD, TEMP, snapshot, specifications as profiles, table
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-create-table-as"
OBSERVER = "mysql_create_table_as_observer.php"
CONTRACT_NAME = "mysql-create-table-as-v1.json"
CONTRACT = "a10a0af3b78e1307374fc41a20ace5c5639659e43197a7caa61b23d1a5d9bd95"
ORDER_CONTRACT = "f4795e89611f1f854d744d70f21e4ff8b7a273c91ef8fa2991c5cb417f09f390"
WIDTH_CONTRACT = "d66a2772798381aeb7e92509591c2e9028cb7ea343d0616f9219c3bcde01e815"
SOURCE_CONTRACT = "9a04d8c983e9484e70279798569fd1d83ef5ac2ca55432fc261bfa4eb4fb0ffe"
DECLARATION_CONTRACT = "aaad27fe95a548871d66f8878aea697079d87add3db128aff7e64e7e1add6ebc"
NATIVE_WIDTH_CONTRACT = "597ce5dfcc3b68fe6558b2a2d2236692c4c29e8f82b867a800bfddf3783c55c7"
POSITIVE_BIGINT = {"legacy": 1000000000, "modern": 100000000}
LEGACY_ORDER = "CREATE TABLE ir_fixture.ir_create_target AS SELECT CAST(17 AS SIGNED) AS value FROM DUAL ORDER BY 1 DESC LIMIT 0"
ANALYSIS_FAILURE = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\AnalysisException"
COMPLETE_SEEDS = True
NAME = "ir_create_target"
SCHEMAS = {"primary": "ir_fixture", "decoy": "ir_ctas_decoy"}
AXES = ("destinationSchema", "destinationKind", "destinationTopology", "ifNotExists", "body", "population")
BARE_BINARY = (*OLD, "mysql-8.0.44", "mysql-8.1.0", "mysql-8.2.0", "mysql-8.3.0")
INVALID = "mysql-create-table-as-invalid-published"
NAME_ERROR = "CREATE TABLE AS requires ASCII column names of 1 to 64 characters without trailing spaces."
FIELD_ERROR = "CREATE TABLE AS requires a table slot, an unpadded integral or NULL literal, or its single signed cast."
BODY_ERROR = "CREATE TABLE AS requires a single projection without partitions or a duplicate policy."
UNIQUE = "CREATE TABLE AS cannot repeat a column name."


def native_width_amendment():
    path = TOOL / "mysql-create-table-as-width-evidence-v1.json"
    require(digest(path) == NATIVE_WIDTH_CONTRACT, "mysql-create-table-as-native-width-contract")
    return json.loads(path.read_text())["predecessorReceipts"]


def former_width_probe(tag, width=129):
    return case(tag, "primary", "table", "absent", False,
                select([[literal(1), f"c{at}"] for at in range(width)]))


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-create-table-as-contract-mismatch")
    order = TOOL / "mysql-source-free-order-v1.json"
    require(digest(order) == ORDER_CONTRACT, "mysql-source-free-order-contract-mismatch")
    rule = json.loads(order.read_text())
    same(LEGACY_ORDER, rule["failure"]["sample"])
    width_path = TOOL / "mysql-create-table-as-integer-width-v1.json"
    require(digest(width_path) == WIDTH_CONTRACT, "mysql-create-table-as-width-contract-mismatch")
    widths = json.loads(width_path.read_text())
    width = widths["materialization"]
    same(POSITIVE_BIGINT, width["minimumPositiveBigint"])
    same(sorted(OLD), sorted(width["legacyProfiles"]))
    sources = TOOL / "mysql-create-table-as-source-v1.json"
    require(digest(sources) == SOURCE_CONTRACT, "mysql-create-table-as-source-admission-mismatch")
    specification = json.loads(path.read_text())
    require(json.loads(sources.read_text())["replacesPreviousNegative"]["condition"] in specification["negative"],
            "mysql-create-table-as-source-retirement")
    declarations = TOOL / "mysql-create-table-as-declarations-v1.json"
    require(digest(declarations) == DECLARATION_CONTRACT, "mysql-create-table-as-declaration-admission")
    retired = json.loads(declarations.read_text())["replacesPreviousNegative"]
    require(retired["condition"] in specification["negative"], "mysql-create-table-as-retired-declaration-negative")
    specification["widthWitness"] = widths["witness"]
    specification["negativeByRelease"] = {rule["failure"]["profile"]: [LEGACY_ORDER]}
    return specification, profiles()[1]


def cast(term):
    return ["signed-cast", term]


def select(columns, where=None, distinct=False, order=(), limit=None):
    return {"source": None, "columns": columns, "where": where, "distinct": distinct, "orderBy": list(order), "limit": limit}


def case(tag, schema, kind, topology, guard, query, variant=0):
    return {"dialect": "mysql", "release": tag, "topology": topology, "variant": variant,
            "mysqlCreateTableAs": {"target": [SCHEMAS[schema], NAME, kind], "ifNotExists": guard, "query": query}}


def columns(role):
    return {
        "literal": [[literal(-2147483648), "lo"], [literal(2147483647), "hi"]],
        "cast": [[cast(literal(-17)), "cast_value"], [cast(["null"]), "cast_null"]],
        "truth-null": [[["boolean", True], "truth"], [["null"], "nil"]],
        "mixed": [[literal(17), "value"], [["null"], "z"], [cast(["boolean", False]), "cast_false"]],
    }[role]


def finite_ids(_, specification, _tag):
    expected = {(schema, kind, topology, guard, body, rows)
                for schema in SCHEMAS for kind in ("table", TEMP)
                for topology in ("absent", "persistent", "temporary", "shadow") for guard in (False, True)
                for body in ("literal", "cast", "truth-null", "mixed") for rows in ("row", "no-row")}
    require(len(expected) == specification["finite"]["identities"], "mysql-create-table-as-grid-size")
    return expected


def finite_id(_, response, item):
    observed = response["mysqlCreateTableAs"]
    schema, relation, kind = observed["target"]
    require(relation == NAME, "mysql-create-table-as-target", observed)
    schema = next(role for role, name in SCHEMAS.items() if name == schema)
    query = observed["query"]
    body = next(role for role in ("literal", "cast", "truth-null", "mixed") if columns(role) == query["columns"])
    return (schema, kind, item["topology"], observed["ifNotExists"], body, "row" if query["where"] is None else "no-row")


def analyze_request(item, sql, named=True):
    return {"dialect": "mysql", "release": item["release"], "sql": sql, "declarations": [],
            **({"currentDatabase": SCHEMAS["primary"]} if named else {})}


def authored(tag):
    qualified = f"{SCHEMAS['primary']}.{NAME}"
    old = tag in OLD
    dual = " FROM DUAL" if old else ""
    examples = [
        (f"CREATE TEMPORARY TABLE {qualified} AS SELECT 1 AS c0", "primary", TEMP, "absent", False,
         select([[literal(1), "c0"]])),
        (f"CREATE TABLE {NAME} AS SELECT 17 AS c", "primary", "table", "absent", False,
         select([[literal(17), "c"]])),
        (f"CREATE TABLE {SCHEMAS['decoy']}.{NAME} ENGINE=InnoDB AS SELECT 23 AS written", "decoy", "table", "absent", False,
         select([[literal(23), "written"]])),
        (f"CREATE TEMPORARY TABLE IF NOT EXISTS {NAME} AS SELECT TRUE AS c", "primary", TEMP, "temporary", True,
         select([[["boolean", True], "c"]])),
        (f"CREATE TABLE {qualified} AS SELECT -2147483648 AS v0, 2147483647 AS V1", "primary", "table", "absent", False,
         select([[literal(-2147483648), "v0"], [literal(2147483647), "V1"]])),
        (f"CREATE TABLE {qualified} AS SELECT 17", "primary", "table", "absent", False,
         select([[literal(17), "17"]])),
        (f"CREATE TEMPORARY TABLE {qualified} AS SELECT NULL AS value{dual} WHERE NULL", "primary", TEMP, "absent", False,
         select([[["null"], "value"]], where=["null"])),
        (LEGACY_ORDER.replace(" ORDER BY 1 DESC", "") if tag == "mysql-5.6.51" else LEGACY_ORDER,
         "primary", "table", "absent", False,
         select([[cast(literal(17)), "value"]], order=[] if tag == "mysql-5.6.51" else [[1, True, False]], limit=[0, 0])),
        (f"CREATE TABLE {qualified} AS SELECT DISTINCT 19 AS value{dual} LIMIT 1, 1", "primary", "table", "absent", False,
         select([[literal(19), "value"]], distinct=True, limit=[1, 1])),
    ]
    for sql, schema, kind, topology, guarded, query in examples:
        item = case(tag, schema, kind, topology, guarded, query)
        yield analyze_request(item, sql), item


def replace_query(item, query):
    return {**item, "mysqlCreateTableAs": {**item["mysqlCreateTableAs"], "query": query}}


def negatives(tag):
    item = case(tag, "primary", "table", "absent", False, select([[literal(1), "c0"]]))
    yield replace_query(item, select([[literal(1), "a"], [literal(2), "A"]])), UNIQUE
    for name in ("", "x" * 65, "x ", "é"):
        yield replace_query(item, select([[literal(1), name]])), NAME_ERROR
    amendment = native_width_amendment()
    yield former_width_probe(tag, amendment["projection"]["replacementProjectedWidth"]), amendment["message"]
    source = {"schema": "ir_fixture", "table": "known", "columns": ["metric"], "relationKind": "view"}
    yield replace_query(item, {**select([[literal(1), "c0"]]), "source": source}), "CREATE TABLE AS requires a source-free projection or one resolved table."
    for term in (["parameter", 1], ["integer", "1", 4, False], cast(["operation", "+", literal(1), literal(2)])):
        yield replace_query(item, select([[term, "c0"]])), FIELD_ERROR
    predicate = ["comparison", "=", literal(1), literal(2)]
    yield replace_query(item, select([[literal(1), "c0"]], where=predicate)), "CREATE TABLE AS requires a constant filter or a checked table predicate."
    yield analyze_request(item, f"CREATE TEMPORARY TABLE {NAME} ENGINE=MEMORY AS SELECT 1 AS c0"), "CREATE TABLE AS requires the native InnoDB storage role."
    yield analyze_request(item, f"CREATE TABLE {NAME} AS SELECT 1 AS c0", named=False), "CREATE TABLE AS requires an absolute target name."
    yield analyze_request(item, f"CREATE TABLE {NAME} (c0 INT NOT NULL, PRIMARY KEY (c0)) AS SELECT 1 AS c0"), \
        "Explicit CREATE TABLE AS definitions do not support a primary key."


def seeded(tag, specification, seed):
    rng = Random(seed)
    axes = specification["finite"]
    for index in range(specification["exploration"]["casesPerSeed"]):
        schema, kind, topology, guarded = (rng.choice(axes[role]) for role in AXES[:4])
        width = 128 if index == 0 else rng.randrange(1, 10)
        fields = []
        for position in range(width):
            value = rng.choice((-2147483648, -1000000000, -1, 0, 999999999, 1000000000, 2147483647,
                                rng.randint(-2147483648, 2147483647)))
            atom = rng.choice((literal(value), ["boolean", bool(rng.getrandbits(1))], ["null"]))
            term = cast(atom) if rng.getrandbits(1) else atom
            label = "x" * 62 + "`1" if index == 0 and position == 0 else f"c{position}"
            fields.append([term, label])
        rng.shuffle(fields)
        where = rng.choice((None, ["boolean", True], ["boolean", False], ["null"]))
        bounds = rng.choice((None, [0, 0], [1, 0], [5, 1], [2147483647, 0]))
        order = []
        for position in rng.sample(range(1, width + 1), min(width, index % 4)):
            descending = bool(rng.getrandbits(1))
            order.append([position, descending, not descending])
        yield case(tag, schema, kind, topology, guarded,
                   select(fields, where, bool(rng.getrandbits(1)), order, bounds), variant=rng.randrange(15))


def requests(_, tag, specification):
    axes = specification["finite"]
    for index, (*target, role, rows) in enumerate(product(*(axes[axis] for axis in AXES))):
        item = case(tag, *target, select(columns(role), where=None if rows == "row" else ["boolean", False]))
        yield f"finite/{index}", item, item, None
    originals = list(authored(tag))
    require(len(originals) == len(specification["analysis"]), "mysql-create-table-as-authored-size")
    same(specification["sharedAdmission"]["previousSql"], originals[0][0]["sql"])
    for index, (request, item) in enumerate(originals):
        yield f"analyze/{index}", request, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, specification, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    witness = specification["widthWitness"]
    require(witness["casesPerRelease"] == 1, "mysql-create-table-as-width-witness-count")
    materialized = select([[literal(term), f"v{position}"] for position, term in enumerate(witness["terms"])])
    item = case(tag, "primary", "table", "absent", False, materialized)
    yield "probe/native-width", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(specification["negative"]), "mysql-create-table-as-negative-size")
    for index, (request, message) in enumerate(invalid):
        yield f"negative/{index}", request, None, {"exception": DIAGNOSTIC, "message": message}
    if tag == "mysql-5.6.51":
        yield f"negative/{len(invalid)}", analyze_request(originals[7][1], LEGACY_ORDER), None, {
            "exception": ANALYSIS_FAILURE, "message": "Unexpected 'ORDER'",
        }


def target_name(definition):
    return ".".join(definition["target"][:2])


def initial_state(tag, target, topology, variant):
    permanent = {}
    for schema, base in zip(SCHEMAS.values(), (11, 41)):
        name = f"{schema}.{NAME}"
        exists = name != target or topology in ("persistent", "shadow")
        permanent[name] = table(tag, "table", [["existing", "INTEGER", True, None]], [], [[None], [base + variant]]) if exists else None
    temporary = {}
    if topology in ("temporary", "shadow"):
        temporary[target] = table(tag, TEMP, [["local_value", "SMALLINT", True, literal(9)]], [], [[None], [120 + variant]])
    return permanent, temporary


def evaluate_field(tag, term, name):
    converted = term[0] == "signed-cast"
    atom = term[1] if converted else term
    nullable = atom[0] == "null"
    if nullable and not converted:
        physical = "binary(0)" if tag in BARE_BINARY else "varbinary(0)"
        return [name, physical, True, None, None], None
    require(atom[0] in ("integer", "boolean", "null"), "mysql-create-table-as-oracle-operand", term)
    value = None if nullable else int(atom[1])
    width = 0 if nullable else len(str(value))
    threshold = POSITIVE_BIGINT["legacy" if tag in OLD else "modern"]
    bare_wide = atom[0] == "integer" and value >= threshold
    if tag in OLD:
        physical = f"{'bigint' if not converted and bare_wide else 'int'}({width})"
    else:
        physical = "bigint" if converted or bare_wide else "int"
    return [name, physical, nullable, None if nullable else "0", None], value


def materialized_table(tag, kind, query):
    fields, values = zip(*(evaluate_field(tag, term, name) for term, name in query["columns"]))
    admitted = query["where"] in (None, ["boolean", True]) and (query["limit"] is None or
                                                               query["limit"][0] > 0 and query["limit"][1] == 0)
    return {"kind": kind, "engine": "InnoDB", "collation": COLLATION, "columns": list(fields), "key": [],
            "rows": [list(values)] if admitted else []}


def prediction(item):
    definition = item["mysqlCreateTableAs"]
    tag, target, kind = item["release"], target_name(definition), definition["target"][2]
    permanent, session = initial_state(tag, target, item["topology"], item["variant"])
    initial = snapshot(permanent, session, [0], [0])
    temporary = kind == TEMP
    affected = session if temporary else permanent
    collision = affected.get(target) is not None
    optional = definition["ifNotExists"]
    diagnostics = [] if not collision else [["Note" if optional else "Error", 1050, f"Table '{NAME}' already exists"]]
    inserted = 0
    if not collision:
        affected[target] = materialized_table(tag, kind, definition["query"])
        inserted = len(affected[target]["rows"])
    effect = ["db-error", "42S01", 1050] if collision and not optional else ["executed", inserted]
    pending = [0, 7]
    committed = [0] if temporary else pending
    after = snapshot(permanent, session, pending, committed)
    if not collision and temporary:
        affected[target] = {**affected[target], "rows": []}
    return {"status": LABEL, "initial": initial, "effect": effect, "diagnostics": diagnostics,
            "warningCount": len(diagnostics), "active": temporary, "after": after,
            "restored": snapshot(permanent, session, committed, committed),
            "afterDisconnect": snapshot(permanent, {}, committed, committed)}


def observer_request(item, sql):
    return {"sql": sql, "target": target_name(item["mysqlCreateTableAs"]), "topology": item["topology"], "variant": item["variant"]}


def observation(raw):
    return raw


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb",
            "mysql-create-table-as-construction", response)
    same(item["mysqlCreateTableAs"], response["mysqlCreateTableAs"])


def check_observation(item, actual, sql):
    expected = prediction(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == LABEL, "mysql-create-table-as-observer-rejected", detail)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, response, _engine, item):
    model = response["mysqlCreateTableAs"]
    key = model["query"]
    schema = next(role for role, location in SCHEMAS.items() if location == model["target"][0])
    for entry in (f"schema/{schema}", f"kind/{model['target'][2]}", f"prior/{item['topology']}", f"guard/{model['ifNotExists']}",
                  f"width/{len(key['columns'])}", f"distinct/{key['distinct']}", f"ordered/{bool(key['orderBy'])}"):
        forms[entry] += 1
    where = key["where"]
    forms["where/" + ("absent" if where is None else "null" if where[0] == "null" else str(where[1]))] += 1
    limit = key["limit"]
    forms["limit/" + ("absent" if limit is None else "zero" if limit[0] == 0 else "offset" if limit[1] else "positive")] += 1
    for term, label in key["columns"]:
        forms[f"role/{term[0]}"] += 1
        if term[0] == "signed-cast":
            forms[f"cast/{term[1][0]}"] += 1
        if term[0] == "integer" and int(term[1]) in (-2147483648, 2147483647, 999999999, 1000000000):
            forms[f"boundary/{term[1]}"] += 1
        if len(label) == 64:
            forms["name/maximum"] += 1


def required_forms(_engine, _tag):
    return {"schema/primary", "schema/decoy", "kind/table", "kind/temporary-table", "guard/True", "guard/False",
            "width/1", "width/128", "name/maximum", "distinct/True", "distinct/False", "ordered/True", "ordered/False",
            *("prior/" + role for role in ("absent", "persistent", "temporary", "shadow")),
            *("where/" + role for role in ("absent", "False", "True", "null")),
            *("limit/" + role for role in ("absent", "zero", "positive", "offset")),
            *("role/" + role for role in ("integer", "boolean", "null", "signed-cast")),
            *("cast/" + role for role in ("integer", "boolean", "null")),
            *("boundary/" + value for value in ("-2147483648", "2147483647", "999999999", "1000000000"))}


def outsiders():
    return [f"DROP TABLE {NAME}", f"CREATE TABLE other AS SELECT 1 AS n",
            f"CREATE TABLE {NAME} AS SELECT 1 AS n; SELECT 1"]


def prepare(_engine, db):
    item = case("mysql-8.4.7", "primary", "table", "absent", False, select(columns("mixed")))
    return {sql: db.request(observer_request(item, sql)) for sql in outsiders()}


def check_preparation(_engine, _environment, preparation):
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation)


def controls():
    item = case("mysql-8.4.7", "decoy", TEMP, "absent", False, select(columns("mixed")))
    expected = prediction(item)
    check_observation(item, expected, "known query-backed declaration")
    copy = target_name(item["mysqlCreateTableAs"])
    changed = deepcopy(expected)
    changed["after"]["visible"][copy]["columns"][1][1] = "binary(0)"
    variants = {"native-type": changed, "lost-rows": {**expected, "after": expected["restored"]},
                "leaked-temporary-definition": {**expected, "afterDisconnect": expected["restored"]}}
    detections = {}
    for name, actual in variants.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detections[name] = error.signal
        else:
            raise Failure("mysql-create-table-as-comparator-accepted", name)
    return {"input": item, "prediction": expected, "mismatches": variants, "detections": detections}


def check_boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-create-table-as-unexpected-fault-response", response)
    raise Failure(INVALID, {"sql": response["sql"], "expected": item["boundary"], "observed": response})


def fault_probe(item, response, db):
    if "boundary" in item:
        check_boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            check_boundary(rule.item, record["healthyResponse"])
            check_boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-create-table-as-replay-accepted-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-mysql/src/Ir/"
    root = ir + "Schema/Query/CreateTableAs.php"
    layout = ir + "Schema/Query/CreateAsLayout.php"
    query = select(columns("mixed"))
    local = case(tag, "decoy", TEMP, "absent", False, query)
    guarded = case(tag, "primary", "table", "persistent", True, query)
    invalid, message = next(negatives(tag))
    boundary = {"boundary": {"exception": DIAGNOSTIC, "message": message}}
    return [
        RenderFault("erase-query-creation-namespace", root, "$this->target->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : ''",
                    "''", local, "meaning-mismatch"),
        RenderFault("erase-query-creation-qualifier", root, "$this->target->quoted()", "$this->target->name->table->quoted()",
                    local, "meaning-mismatch"),
        RenderFault("erase-query-creation-operand", root, "$this->query->toString()",
                    "str_replace(', NULL AS `z`', '', $this->query->toString())", local, "meaning-mismatch"),
        RenderFault("erase-query-creation-signed-cast", ir + "SelectSql.php",
                    "'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS SIGNED)'",
                    "$this->expression($expression->operand, $source, $offset, $qualify)",
                    case(tag, "decoy", TEMP, "absent", False, select(columns("cast"))), "meaning-mismatch"),
        RenderFault("invert-query-creation-guard", root, "$this->ifNotExists ? 'IF NOT EXISTS '", "!$this->ifNotExists ? 'IF NOT EXISTS '",
                    guarded, "meaning-mismatch"),
        RenderFault("admit-repeated-query-creation-heading", layout, "!isset($seen[$key])", "true", boundary, INVALID, invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
