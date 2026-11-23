"""Compare resolved MySQL VARCHAR inheritance with physical column metadata and rows."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_presence import null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, project, query
from server_cases import observation
from server_join_cases import slot
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-inheritance"
CONTRACT = "5bd0a3474cd47e20ec070aca435175f97941a2a40c96bb40d69612687f93d28d"
CASES = {
    "collation-only": ("ir_varchar_inherit_a", "", "COLLATE utf8mb4_bin"),
    "charset-then-collation": ("ir_varchar_inherit_b", "", "DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_bin"),
    "collation-then-charset": ("ir_varchar_inherit_c", "", "DEFAULT COLLATE utf8mb4_bin DEFAULT CHARSET=utf8mb4"),
    "column-override": ("ir_varchar_inherit_d", "COLLATE utf8mb4_bin", "COLLATE utf8mb4_general_ci"),
}
LEGACY = ("mysql-5.6.51", "mysql-5.7.44")
REVERSED = "collation-then-charset"
HEADING = ("label", "alternate", "id")
TYPES = (["varchar", 8, COLLATION], ["varchar", 8, COLLATION, False], "int")
ROWS = [("a", "Z", 10), ("A", "a", 20), ("a ", "B", 30), (None, "", 40), ("𐐷", "X", 50),
        ("a\x00", "Y", 60), ("a", "Z", 10), ("", "only", 70)]


def specifications():
    path, profile = TOOL / "mysql-varchar-inheritance-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-inheritance-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def definition(origin):
    name, column, options = CASES[origin]
    return (f"CREATE TABLE {SCHEMA}.{name} (label VARCHAR(8) {column}, alternate VARCHAR(8) {column} NOT NULL, id INT) "
            f"{options} ENGINE=InnoDB")


def prepare(_engine, db):
    names = [case[0] for case in CASES.values()]
    values = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in ROWS)
    commands = [command for key, name in zip(CASES, names) for command in (
        f"DROP TABLE IF EXISTS {SCHEMA}.{name}", definition(key),
        f"INSERT INTO {SCHEMA}.{name} (label, alternate, id) VALUES {values}",
    )]
    receipt = db.request({"fixture": commands})
    listed = ", ".join("'" + name + "'" for name in names)
    rows = db.request({"sql": "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, "
                              "CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS "
                              f"WHERE TABLE_SCHEMA = '{SCHEMA}' AND TABLE_NAME IN ({listed}) "
                              "ORDER BY TABLE_NAME, ORDINAL_POSITION"})
    data = {name: observation(db.request({"sql": f"SELECT label, alternate, id FROM {SCHEMA}.{name}"})) for name in names}
    contrast = db.request({"sql": f"SELECT label = 'a' AS result FROM {SCHEMA}.{CASES[REVERSED][0]} WHERE id = 20"})
    return {"fixture": receipt, "columns": rows["rows"], "data": data, "nativeReverseCase": contrast}


def check_preparation(engine, environment, actual):
    require(engine == "mysql", "mysql-varchar-inheritance-engine", engine)
    same({"status": "fixture"}, actual["fixture"])
    version = environment["database"][0].split("-")[0]
    legacy = "mysql-" + version in LEGACY
    expected_columns = []
    for origin, (name, _, _) in CASES.items():
        collation = "utf8mb4_general_ci" if legacy and origin == REVERSED else COLLATION
        expected_columns.extend([[name, "label", "varchar", "utf8mb4", collation, 8, "YES"],
                                 [name, "alternate", "varchar", "utf8mb4", collation, 8, "NO"],
                                 [name, "id", "int", None, None, None, "YES"]])
    same({"status": "rows", "fields": [["result", "LONGLONG"]], "rows": [[int(legacy)]]}, actual["nativeReverseCase"])
    same(sorted(case[0] for case in CASES.values()), sorted(actual["data"]))
    same(expected_columns, actual["columns"])
    raw = {"status": "rows", "fields": [["label", "VAR_STRING"], ["alternate", "VAR_STRING"], ["id", "LONG"]],
           "rows": ROWS}
    for result in actual["data"].values():
        same(observation(raw), result)


def source(origin):
    return {"schema": SCHEMA, "table": CASES[origin][0], "columns": list(HEADING), "columnTypes": list(TYPES)}


def selection(name):
    return {"all": None, "present": null_test(field(0), True), "empty": ["boolean", False]}[name]


def projection(name):
    return {"field": field(0), "equality": comparison("=", field(0), text("a ")),
            "null-safe": comparison("<=>", field(0), ["null"])}[name]


def finite(tag, origin, expression, filter_):
    return {**project(tag, source(origin), [[projection(expression), "value"]]), "where": selection(filter_)}


def admitted_origins(tag, origins):
    return [name for name in origins if tag not in LEGACY or name != REVERSED]


def finite_ids(_, spec, tag):
    b = spec["finite"]
    identities = set(product(admitted_origins(tag, b["origin"]), b["projection"], b["filter"]))
    require(len(identities) == b["legacyIdentities" if tag in LEGACY else "identities"], "mysql-varchar-inheritance-finite-size", len(identities))
    return identities


def finite_id(_, actual, _item):
    origin = next(key for key, entry in CASES.items() if entry[0] == actual["source"]["table"])
    (term, _), = actual["columns"]
    roles = {name: projection(name) for name in ("field", "equality", "null-safe")}
    filters = {name: selection(name) for name in ("all", "present", "empty")}
    return (origin, next(name for name, expected in roles.items() if expected == term),
            next(name for name, expected in filters.items() if expected == actual["where"]))


def analyzed(tag):
    for key in admitted_origins(tag, CASES):
        name = CASES[key][0]
        sql = (f"SELECT r.label AS value, label <=> NULL AS null_value, r.alternate AS other "
               f"FROM {SCHEMA}.{name} r")
        projection_ = [[field(0), "value"], [projection("null-safe"), "null_value"], [field(1), "other"]]
        yield query(tag, sql, [definition(key)]), project(tag, source(key), projection_)


def negative(tag):
    name = f"{SCHEMA}.{CASES['collation-only'][0]}"
    plain = f"CREATE TABLE {name} (label VARCHAR(8))"
    sql = f"SELECT label AS value FROM {name}"
    declarations = (
        plain, plain.replace("VARCHAR(8)", "VARCHAR(8) CHARACTER SET utf8mb4") + " COLLATE utf8mb4_bin",
        plain.replace("VARCHAR(8)", "VARCHAR(8) COLLATE utf8mb4_general_ci") + " COLLATE utf8mb4_bin",
        plain + " CHARACTER SET latin1 COLLATE utf8mb4_bin",
        plain + " COLLATE utf8mb4_general_ci COLLATE utf8mb4_bin",
    )
    for declaration in declarations:
        yield query(tag, sql, [declaration]), "The text slot requires an explicit utf8mb4_bin collation."
    if tag in LEGACY:
        qualified = f"{SCHEMA}.{CASES[REVERSED][0]}"
        yield query(tag, f"SELECT label AS value FROM {qualified}", [definition(REVERSED)]), "The text slot requires an explicit utf8mb4_bin collation."


def seeded(tag, seed, budget):
    rng = Random(seed)
    origins = admitted_origins(tag, CASES)
    for index in range(budget):
        origin = origins[index % len(origins)]
        items = []
        for position in range(index % 3 + 1):
            reference = field(rng.randrange(2))
            terms = [reference, comparison(rng.choice(("=", "<=>", "<")), reference, text(rng.choice(("a  ", "A", "", "é", "𐐷", "a\x00"))))]
            items.append([rng.choice(terms), rng.choice((f"r{position}", "same"))])
        after = rng.choice((None, selection("present"), selection("empty"), comparison("<=>", field(0), ["null"])))
        yield {**project(tag, source(origin), items), "where": after}


def requests(_, tag, spec):
    b = spec["finite"]
    for index, vector in enumerate(product(admitted_origins(tag, b["origin"]), b["projection"], b["filter"])):
        item = finite(tag, *vector)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]) - int(tag in LEGACY), "mysql-varchar-inheritance-analysis-incomplete")
    for index, (sql, item) in enumerate(inputs):
        yield f"analysis/{index}", sql, item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (sql, message) in enumerate(negative(tag)):
        yield f"negative/{index}", sql, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def address(term, src):
    name = src["columns"][term[1]]
    kind = src["columnTypes"][term[1]]
    require((len(term) < 3 or term[2] == 0) and kind == TYPES[HEADING.index(name)] and (term[0] == "text-slot") == (kind != "int"),
            "mysql-varchar-inheritance-field-type", [term, src])
    return name


def graph(item):
    src = item["source"]

    def resolve(term):
        if term is None:
            return None
        role, *parts = term
        if role in ("text-slot", "slot"):
            return role, address(term, src)
        if role == "text-comparison":
            return role, parts[0], parts[1], resolve(parts[2]), resolve(parts[3])
        if role == "null-test":
            return role, parts[0], resolve(parts[1])
        require(role in ("boolean", "text", "null"), "mysql-varchar-inheritance-role", term)
        return tuple(term)

    return (src["schema"], src["table"], tuple((resolve(value), name) for value, name in item["columns"]), resolve(item["where"]))


def check_value(item, actual):
    require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/"),
            "mysql-varchar-inheritance-positive-rejected", actual)
    same(graph(item), graph(actual))


def expected(item):
    src = item["source"]

    def atom(node, row):
        if node[0] == "null":
            return None
        if node[0] == "text":
            return node[1]
        return row[HEADING.index(address(node, src))]

    def evaluate(term, row):
        if term[0] == "text-comparison":
            value = predicate_value(term, lambda node: atom(node, row))
            return None if value is None else int(value)
        return atom(term, row)

    return observation({"status": "rows", "fields": [[name, "VAR_STRING" if term[0] == "text-slot" else "LONGLONG"]
                                                        for term, name in item["columns"]],
                        "rows": [[evaluate(term, row) for term, _ in item["columns"]]
                                 for row in ROWS if admits(item["where"], lambda node: atom(node, row))]})


def check_observation(item, actual, sql):
    wanted = expected(item)
    detail = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "mysql-varchar-inheritance-native-rejection", detail)
    require(wanted == actual, "meaning-mismatch", detail)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-varchar-inheritance-fault-native")


def sample_forms(forms, actual, *_):
    _, name, columns, predicate = graph(actual)
    forms["source/" + name] += 1
    forms[f"width/{len(columns)}"] += 1
    forms["filter/" + (predicate[0] if predicate else "all")] += 1
    for term, _ in columns:
        forms["result/" + term[0]] += 1
        if term[0] == "text-slot":
            forms["field/" + term[1]] += 1
        else:
            forms["comparison/" + term[1]] += 1


def required_forms(_, tag):
    return {*(f"source/{CASES[origin][0]}" for origin in admitted_origins(tag, CASES)), "width/1", "width/2", "width/3", "field/label",
            "field/alternate", "result/text-slot", "result/text-comparison", "filter/all", "filter/boolean",
            "filter/text-comparison", "filter/null-test", "comparison/=", "comparison/<=>", "comparison/<"}


def controls():
    item = finite("mysql-9.1.0", "collation-only", "equality", "all")
    wanted = expected(item)
    check_observation(item, wanted, "native binary equality")
    lost = {**wanted, "rows": wanted["rows"][:-1]}
    try:
        check_observation(item, lost, "drop one physical row")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-varchar-inheritance-wrong-control", error.detail)
    else:
        raise Failure("mysql-varchar-inheritance-comparator-accepted")
    return {"input": item, "expected": wanted, "mismatch": lost}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    ordered = "$left . ' ' . $comparison->operator->value . ' ' . $right"
    return [
        RenderFault("read-alternate-inherited-varchar-field", renderer, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 1 : $expression->slot]->quoted()",
                    finite(tag, "collation-only", "field", "all"), "meaning-mismatch"),
        RenderFault("erase-inherited-varchar-null-safe-equality", renderer, "$comparison->operator->value", "'='",
                    finite(tag, "charset-then-collation", "null-safe", "all"), "meaning-mismatch"),
        RenderFault("replace-inherited-varchar-binary-comparison", renderer, ordered,
                    "$left . ' COLLATE utf8mb4_general_ci ' . $comparison->operator->value . ' ' . $right",
                    finite(tag, "charset-then-collation" if tag in LEGACY else REVERSED, "equality", "all"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
