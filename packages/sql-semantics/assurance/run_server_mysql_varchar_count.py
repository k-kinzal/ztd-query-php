"""Compare native VARCHAR COUNT, DISTINCT and grouping with an independent partition model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_presence import logical, null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, query
from server_cases import observation as unordered
from server_grouped_cases import aggregate, case, ordered
from server_join_cases import slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-count"
CONTRACT = "7c91770c3f26ffec66b122258fc64ef09231f59044a32d7c09ca7fe89ae8838b"
PRIMARY = "ir_varchar_count"
EMPTY = "ir_varchar_count_empty"
HEADING = ("label", "bucket", "alternate", "empty_value")
TYPES = (["varchar", 8, COLLATION], "int", ["varchar", 8, COLLATION], ["varchar", 0, COLLATION])
ROWS = {
    PRIMARY: [("a ", 2, "A", ""), ("a", 2, None, None), ("A", 2, "a", ""), ("a  ", 2, "a  ", ""),
              (None, 2, "z", None), ("a\x00", None, "a ", None), ("é", None, None, ""),
              ("é", 1, "𐐷", ""), ("𐐷", 1, "é", None), ("", -1, None, ""), (None, -1, None, None),
              (None, 0, "a\x00", ""), (None, 0, None, None)],
    EMPTY: [],
}


def ddl(name):
    return (f"CREATE TABLE {SCHEMA}.{name} (label VARCHAR(8) COLLATE utf8mb4_bin, bucket INT, "
            "alternate VARCHAR(8) COLLATE utf8mb4_bin, empty_value VARCHAR(0) COLLATE utf8mb4_bin) ENGINE=InnoDB")


DECLARATIONS = list(map(ddl, ROWS))


def specifications():
    path, profile = TOOL / "mysql-varchar-count-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-count-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def table(name):
    return {"schema": SCHEMA, "table": name, "columns": list(HEADING), "columnTypes": list(TYPES)}


def prepare(_engine, db):
    values = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in ROWS[PRIMARY])
    result = db.request({"fixture": [*(f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in ROWS), *DECLARATIONS,
                                     f"INSERT INTO {SCHEMA}.{PRIMARY} VALUES {values}"]})
    types = db.request({"sql": "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME "
                               "FROM information_schema.COLUMNS "
                               f"WHERE TABLE_SCHEMA = '{SCHEMA}' AND TABLE_NAME IN ('{PRIMARY}', '{EMPTY}') "
                               "ORDER BY TABLE_NAME, ORDINAL_POSITION"})
    data = {name: unordered(db.request({"sql": f"SELECT label, bucket, alternate, empty_value FROM {SCHEMA}.{name}"})) for name in ROWS}
    return {"fixture": result, "types": types["rows"], "data": data}


def check_preparation(engine, _environment, actual):
    require(engine == "mysql", "mysql-varchar-count-engine", engine)
    same({"status": "fixture"}, actual["fixture"])
    expected_types = [[table, field, "int", None, None] if kind == "int" else [table, field, "varchar", kind[1], COLLATION]
                      for table, (field, kind) in product(sorted(ROWS), zip(HEADING, TYPES))]
    same(expected_types, actual["types"])
    metadata = [[name, "LONG" if kind == "int" else "VAR_STRING"] for name, kind in zip(HEADING, TYPES)]
    for name, rows in ROWS.items():
        same(unordered({"status": "rows", "fields": metadata, "rows": rows}), actual["data"][name])


def ref(name):
    return field(HEADING.index(name))


def filtered(name):
    return {"all": None, "present": null_test(ref(name), True), "empty": ["boolean", False]}


def grouped(tag, source, keys, columns, predicate=None):
    return case("mysql", tag, table(source), keys, columns, predicate)


def finite(tag, name, distinct, grouping, selection):
    keys = [] if grouping == "global" else [slot(1)]
    columns = [[slot(1), "bucket"]] if keys else []
    columns.append([aggregate("count", ref(name), distinct), "n"])
    return grouped(tag, PRIMARY, keys, columns, filtered(name)[selection])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = set(product(b["fields"], b["distinct"], b["grouping"], b["filtered"]))
    require(len(identities) == b["identities"], "mysql-varchar-count-finite-size", len(identities))
    return identities


def finite_id(_, observed, _item):
    model = observed["grouped"]
    role, operator, reference, distinct = model["columns"][-1][0]
    require((role, operator) == ("aggregate", "count") and model["keys"] in ([], [slot(1)]),
            "mysql-varchar-count-unexpected-finite-shape", model)
    name = address(reference, model["source"])
    return (name, distinct, "by-bucket" if model["keys"] else "global",
            next(label for label, term in filtered(name).items() if term == model["where"]))


def analyzed(tag):
    target, empty = f"{SCHEMA}.{PRIMARY}", f"{SCHEMA}.{EMPTY}"
    yield f"SELECT COUNT(r.label) AS n FROM {target} r", finite(tag, "label", False, "global", "all")
    yield f"SELECT COUNT(DISTINCT label) AS n FROM {target}", finite(tag, "label", True, "global", "all")
    yield (f"SELECT bucket, COUNT(DISTINCT alternate) AS n FROM {target} WHERE alternate IS NOT NULL GROUP BY bucket",
           finite(tag, "alternate", True, "by-bucket", "present"))
    term = comparison("<=>", ref("label"), text("a"))
    yield (f"SELECT COUNT(empty_value) AS n FROM {target} WHERE label <=> 'a'",
           grouped(tag, PRIMARY, [], [[aggregate("count", ref("empty_value")), "n"]], term))
    output = [[aggregate("count", ref("label")), "n"]]
    yield f"SELECT COUNT(label) AS n FROM {empty}", grouped(tag, EMPTY, [], output)
    keys = [[slot(1), "bucket"], [aggregate("count", ref("label"), True), "n"]]
    yield f"SELECT bucket, COUNT(DISTINCT label) AS n FROM {empty} GROUP BY bucket", grouped(tag, EMPTY, [slot(1)], keys)


def negative(tag):
    target = f"{SCHEMA}.{PRIMARY}"
    scope = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield grouped(tag, PRIMARY, [], [[aggregate("count", field(1)), "n"]]), scope
    yield grouped(tag, PRIMARY, [], [[aggregate("count", slot(0)), "n"]]), "The aggregate slot must belong to the group input."
    yield grouped(tag, PRIMARY, [], [[aggregate("count", field(0, 1)), "n"]]), "The aggregate argument must read its single input occurrence."
    yield query(tag, f"SELECT SUM(label) AS n FROM {target}", DECLARATIONS), "SUM requires a signed INT argument."
    yield (query(tag, f"SELECT COUNT(label) AS n FROM {target}", [ddl(PRIMARY).replace(COLLATION, "utf8mb4_general_ci")]),
           "The text slot requires an explicit utf8mb4_bin collation.")


def seeded(tag, seed, budget):
    rng = Random(seed)
    fields = ("label", "alternate", "empty_value")
    terms = (None, ["boolean", False], comparison("<=>", ref("label"), text("a")), null_test(ref("alternate")),
             logical("OR", comparison(">=", ref("label"), text("A")), null_test(ref("empty_value"))))
    for i in range(budget):
        keys = [slot(1)] * (i % 3)
        outputs = [[slot(1), "bucket"]] if keys and i % 2 else []
        outputs += [[aggregate("count", ref(rng.choice(fields)), bool(rng.getrandbits(1))), "n"] for _ in range(i % 4 + 1)]
        if i % 3 == 0:
            outputs.insert(rng.randrange(len(outputs) + 1), [aggregate("count"), "n"])
        name = EMPTY if i % 7 == 0 else PRIMARY
        yield grouped(tag, name, keys, outputs, terms[i % len(terms)])


def requests(_, tag, spec):
    b = spec["finite"]
    for index, key in enumerate(product(b["fields"], b["distinct"], b["grouping"], b["filtered"])):
        item = finite(tag, *key)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "mysql-varchar-count-analysis-incomplete")
    for index, (sql, item) in enumerate(inputs):
        yield f"analysis/{index}", query(tag, sql, DECLARATIONS), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def address(term, source):
    name = source["columns"][term[1]]
    kind = source["columnTypes"][term[1]]
    require((len(term) < 3 or term[2] == 0) and kind == TYPES[HEADING.index(name)]
            and term[0] == ("slot" if kind == "int" else "text-slot"),
            "mysql-varchar-count-argument-type", [term, source])
    return name


def predicate_shape(node, source):
    if node is None:
        return None
    role, *parts = node
    if role in ("slot", "text-slot"):
        return role, address(node, source)
    if role == "logic":
        return role, parts[0], predicate_shape(parts[1], source), predicate_shape(parts[2], source)
    if role == "text-comparison":
        return role, parts[0], parts[1], predicate_shape(parts[2], source), predicate_shape(parts[3], source)
    if role == "null-test":
        return role, parts[0], predicate_shape(parts[1], source)
    require(role in ("text", "boolean"), "mysql-varchar-count-predicate-role", node)
    return tuple(node)


def graph(item):
    model = item["grouped"]
    source = model["source"]

    def resolve(term):
        if term[0] == "aggregate":
            role, operation, argument, distinct = term
            return role, operation, None if argument is None else address(argument, source), distinct
        return term[0], address(term, source)

    return (source["schema"], source["table"], tuple(map(resolve, model["keys"])),
            tuple((resolve(term), label) for term, label in model["columns"]), predicate_shape(model["where"], source))


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/grouped",
            "mysql-varchar-count-positive-rejected", response)
    same(graph(item), graph(response))


def expected(item):
    model = item["grouped"]
    source = model["source"]
    keys = [HEADING.index(address(term, source)) for term in model["keys"]]
    groups = {} if keys else {(): []}

    def resolve(term, row):
        return term[1] if term[0] == "text" else row[HEADING.index(address(term, source))]

    for row in ROWS[source["table"]]:
        if admits(model["where"], lambda term: resolve(term, row)):
            group = tuple(row[index] for index in keys)
            groups.setdefault(group, []).append(row)

    def value(term, records):
        if term[0] == "slot":
            return resolve(term, records[0])
        require(term[0:2] == ["aggregate", "count"], "mysql-varchar-count-oracle-term", term)
        if term[2] is None:
            return len(records)
        present = [resolve(term[2], row) for row in records if resolve(term[2], row) is not None]
        return len({entry.rstrip(" ") for entry in present}) if term[3] else len(present)

    partition = sorted(groups, key=lambda key: tuple((item is not None, item) for item in key)) if ordered(item) else groups
    result = {"status": "rows", "fields": [[label, "LONG" if term[0] == "slot" else "LONGLONG"] for term, label in model["columns"]],
              "rows": [[value(term, groups[key]) for term, _ in model["columns"]] for key in partition]}
    return result if ordered(item) else unordered(result)


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    predicted = expected(item)
    witness = {"sql": sql, "expected": predicted, "observed": actual}
    require(actual["status"] == "rows", "mysql-varchar-count-native-rejection", witness)
    require(predicted == (actual if ordered(item) else unordered(actual)), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-varchar-count-fault-native")


def sample_forms(forms, response, *_):
    _, table_name, keys, columns, predicate = graph(response)
    forms[f"width/{len(columns)}"] += 1
    forms["table/" + table_name] += 1
    forms["filter/" + ("none" if predicate is None else predicate[0])] += 1
    forms["group/repeated" if len(keys) > 1 else "group/key" if keys else "group/global"] += 1
    labels = [label for _, label in columns]
    if len(set(labels)) != len(labels):
        forms["label/repeated"] += 1
    for term, _ in columns:
        if term[0] == "aggregate":
            forms["count/" + (term[2] or "rows")] += 1
            forms[f"distinct/{term[3]}"] += 1
        else:
            forms["output/key"] += 1


def required_forms(*_):
    return {"width/1", "width/2", "width/3", "label/repeated", "output/key", "group/repeated", "group/key", "group/global",
            "distinct/True", "distinct/False", "count/label", "count/alternate", "count/empty_value", "count/rows",
            "table/" + PRIMARY, "table/" + EMPTY, "filter/none", "filter/boolean", "filter/text-comparison", "filter/logic"}


def controls():
    item = finite("mysql-5.7.44", "label", True, "by-bucket", "all")
    reference = expected(item)
    check_observation(item, reference, "registered VARCHAR COUNT")
    changed = {**reference, "rows": list(reversed(reference["rows"]))}
    try:
        check_observation(item, changed, "reverse legacy grouped order")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-varchar-count-wrong-control", error.detail)
    else:
        raise Failure("mysql-varchar-count-comparator-accepted")
    return {"input": item, "expected": reference, "mismatch": changed}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/Aggregation/GroupedSql.php"
    native = "$renderer->expression($value->argument, $source)"
    distinct = finite(tag, "label", True, "global", "all")
    ordinary = finite(tag, "label", False, "global", "all")
    filtered_case = finite(tag, "label", True, "global", "empty")
    return [
        RenderFault("erase-varchar-count-distinct", renderer, "($value->distinct ? 'DISTINCT ' : '')", "''", distinct, "meaning-mismatch"),
        RenderFault("count-varchar-null-as-row", renderer, "$value->argument === null ? '*'", "$value->argument !== null ? '*'", ordinary, "meaning-mismatch"),
        RenderFault("read-alternate-varchar-count-field", renderer, native,
                    "$renderer->expression(new \\SqlSemantics\\Platform\\MySql\\Ir\\From\\TextColumnReference(2), $source)",
                    ordinary, "meaning-mismatch"),
        RenderFault("erase-varchar-count-case-distinction", renderer, native,
                    native + " . ' COLLATE utf8mb4_general_ci'", distinct, "meaning-mismatch"),
        RenderFault("erase-varchar-count-filter", renderer, "if ($query->where !== null)", "if (false)", filtered_case, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
