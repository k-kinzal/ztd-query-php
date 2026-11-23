"""Compare native MySQL text extrema with an independent PAD SPACE model."""

from functools import lru_cache
from itertools import permutations, product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_explicit_text_collation import observer_request
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_count import (
    DECLARATIONS, EMPTY, HEADING, PRIMARY, ROWS, address, check_preparation as check_tables, check_value, ddl, graph,
    grouped, observation, prepare as prepare_tables, ref,
)
from run_server_mysql_varchar_presence import null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, query
from server_grouped_cases import aggregate, ordered
from server_join_cases import slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-extrema"
CONTRACT = "a594302b5388ea5a82a95db7d02c382e674baf61ef5f70b532420a8ab621512e"
FILTERS = {"all": None, "tie": comparison("=", ref("label"), text("a")), "empty": ["boolean", False]}


@lru_cache(maxsize=1)
def specifications():
    path, profile = TOOL / "mysql-varchar-extrema-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-extrema-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    tables = prepare_tables(engine, db)
    probes = {name: f"SELECT COERCIBILITY(COUNT(*)) AS count_kind, COERCIBILITY(bucket) AS key_kind FROM {SCHEMA}.{name}"
              for name in ROWS}
    return {"tables": tables, "metadata": {name: {"sql": sql, "observed": db.request({"sql": sql})} for name, sql in probes.items()}}


def check_preparation(engine, environment, preparation):
    check_tables(engine, environment, preparation["tables"])
    count_kind = specifications()[0]["countCoercibility"]["mysql-" + environment["database"][0]]
    for name in ROWS:
        response = preparation["metadata"][name]["observed"]
        same("rows", response["status"])
        same(["count_kind", "key_kind"], [field[0] for field in response["fields"]])
        same([[count_kind, 5]], response["rows"])


def finite_space(specification):
    finite = specification["finite"]
    return product(*(finite[dimension] for dimension in ("operators", "fields", "distinct", "grouping", "filters")))


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    identities = {(operator, field_name, distinct, grouping, restriction)
                  for operator in finite["operators"] for field_name in finite["fields"] for distinct in finite["distinct"]
                  for grouping in finite["grouping"] for restriction in finite["filters"]}
    require(len(identities) == finite["identities"], "mysql-varchar-extrema-finite-size", len(identities))
    return identities


def finite(tag, operation, name, distinct, grouping, selection):
    keys = [] if grouping == "global" else [slot(1)]
    columns = [[slot(1), "bucket"]] if keys else []
    return grouped(tag, PRIMARY, keys, [*columns, [aggregate(operation, ref(name), distinct), "value"]], FILTERS[selection])


def finite_id(_, observed, _item):
    model = observed["grouped"]
    role, operator, term, distinct = model["columns"][-1][0]
    require(role == "aggregate" and model["keys"] in ([], [slot(1)]), "mysql-varchar-extrema-finite-shape", model)
    restriction = next(label for label, predicate in FILTERS.items() if predicate == model["where"])
    return operator, address(term, model["source"]), distinct, "by-bucket" if model["keys"] else "global", restriction


def analyzed(tag):
    target, empty = f"{SCHEMA}.{PRIMARY}", f"{SCHEMA}.{EMPTY}"
    yield f"SELECT MIN(r.label) AS value FROM {target} r", finite(tag, "min", "label", False, "global", "all")
    yield f"SELECT MAX(DISTINCT alternate) AS value FROM {target}", finite(tag, "max", "alternate", True, "global", "all")
    pair = [[aggregate(operation, ref("label")), "value"] for operation in ("min", "max")]
    yield (f"SELECT MIN(label) AS value, MAX(label) AS value FROM {target} WHERE label = 'a'",
           grouped(tag, PRIMARY, [], pair, FILTERS["tie"]))
    yield (f"SELECT bucket, MAX(label) AS value FROM {target} GROUP BY bucket",
           finite(tag, "max", "label", False, "by-bucket", "all"))
    yield f"SELECT MIN(label) AS value FROM {empty}", grouped(tag, EMPTY, [], pair[:1])
    yield (f"SELECT bucket, MAX(label) AS value FROM {empty} GROUP BY bucket",
           grouped(tag, EMPTY, [slot(1)], [[slot(1), "bucket"], pair[1]]))


def negative(tag):
    target = f"{SCHEMA}.{PRIMARY}"
    yield (grouped(tag, PRIMARY, [], [[aggregate("min", field(1)), "value"]]),
           "A VARCHAR slot must belong to a visible physical input occurrence.")
    yield grouped(tag, PRIMARY, [], [[aggregate("max", slot(0)), "value"]]), "The aggregate slot must belong to the group input."
    yield (grouped(tag, PRIMARY, [], [[aggregate("min", field(0, 1)), "value"]]),
           "The aggregate argument must read its single input occurrence.")
    yield query(tag, f"SELECT SUM(label) AS value FROM {target}", DECLARATIONS), "SUM requires a signed INT argument."
    yield (query(tag, f"SELECT MAX(label) AS value FROM {target}", [ddl(PRIMARY).replace(COLLATION, "utf8mb4_general_ci")]),
           "The text slot requires an explicit utf8mb4_bin collation.")


def seeded(tag, seed, budget):
    rng = Random(seed)
    fields = ("label", "alternate", "empty_value")
    terms = (*FILTERS.values(), null_test(ref("alternate")), comparison(">=", ref("label"), text("A")))
    for index in range(budget):
        keys = [slot(1)] * (index % 3)
        columns = [[slot(1), "bucket"]] if keys and index % 2 else []
        for position in range(index % 4 + 1):
            value = aggregate(rng.choice(("min", "max")), ref(rng.choice(fields)), bool(rng.getrandbits(1)))
            columns.append([value, "value" if index % 2 else f"value{position}"])
        if index % 3 == 0:
            columns.insert(rng.randrange(len(columns) + 1), [aggregate("count"), "n"])
        yield grouped(tag, EMPTY if index % 7 == 0 else PRIMARY, keys, columns, terms[index % len(terms)])


def requests(_, tag, specification):
    for index, vector in enumerate(finite_space(specification)):
        item = finite(tag, *vector)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(specification["analysis"]), "mysql-varchar-extrema-analysis-incomplete")
    for index, (sql, item) in enumerate(inputs):
        yield f"analysis/{index}", query(tag, sql, DECLARATIONS), item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def representatives(operation, values):
    present = {value for value in values if value is not None}
    if not present:
        return [None]
    width = max(map(len, present))
    weights = {value: value.ljust(width, " ").encode("utf-8") for value in present}
    extreme = {"min": min, "max": max}[operation](weights.values())
    return sorted(value for value, weight in weights.items() if weight == extreme)


def expected(item):
    _, name, keys, columns, _ = graph(item)
    model = item["grouped"]
    key_slots = [HEADING.index(column) for _, column in keys]
    groups = {} if key_slots else {(): []}

    def resolve(term, record):
        return term[1] if term[0] == "text" else record[HEADING.index(address(term, model["source"]))]

    for row in ROWS[name]:
        if admits(model["where"], lambda term: resolve(term, row)):
            groups.setdefault(tuple(row[slot] for slot in key_slots), []).append(row)

    def allowed(term, records):
        if term[0] == "slot":
            return [records[0][HEADING.index(term[1])]]
        _, operator, argument, _ = term
        if operator == "count":
            require(argument is None, "mysql-varchar-extrema-count-role", term)
            return [len(records)]
        position = HEADING.index(argument)
        return representatives(operator, [row[position] for row in records])

    kinds = ["LONG" if term[0] == "slot" else "LONGLONG" if term[1] == "count" else "VAR_STRING" for term, _ in columns]
    count_kind = specifications()[0]["countCoercibility"][item["release"]]
    metadata = {"LONG": ["binary", "binary", 5], "LONGLONG": ["binary", "binary", count_kind],
                "VAR_STRING": ["utf8mb4", COLLATION, 2]}
    keys_in_order = sorted(groups, key=lambda key: tuple((value is not None, value) for value in key)) if ordered(item) else groups
    return {"fields": [[label, kind] for (_, label), kind in zip(columns, kinds)],
            "textMetadata": [metadata[kind] for kind in kinds],
            "allowedRows": [[allowed(term, groups[key]) for term, _ in columns] for key in keys_in_order]}


def matches(item, prediction, actual):
    if any(json.dumps(prediction[key]) != json.dumps(actual[key]) for key in ("fields", "textMetadata")):
        return False
    rows = actual["rows"]
    permitted = prediction["allowedRows"]
    if len(rows) != len(permitted):
        return False

    def fits(row, groups):
        return len(row) == len(groups) and all(any(type(value) is type(member) and value == member for member in cell)
                                               for value, cell in zip(row, groups))

    # These fixtures contain at most six groups. Equal-length pairings preserve duplicate rows.
    return any(all(fits(row, group) for row, group in zip(rows, pairing))
               for pairing in ((permitted,) if ordered(item) else permutations(permitted)))


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "mysql-varchar-extrema-native-rejection", witness)
    require(matches(item, prediction, actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    prediction = expected(item)
    same(prediction, witness["expected"])
    actual = witness["observed"]
    require(actual["status"] == "rows" and not matches(item, prediction, actual), "mysql-varchar-extrema-fault-evidence", witness)


def sample_forms(forms, response, _engine, item):
    _, name, keys, columns, predicate = graph(response)
    forms["table/" + name] += 1
    forms["filter/" + ("none" if predicate is None else predicate[0])] += 1
    forms["group/repeated" if len(keys) > 1 else "group/key" if keys else "group/global"] += 1
    if len({label for _, label in columns}) < len(columns):
        forms["label/repeated"] += 1
    for term, _ in columns:
        if term[0] != "aggregate":
            forms["output/key"] += 1
        elif term[1] == "count":
            forms["output/count"] += 1
        else:
            forms["operator/" + term[1]] += 1
            forms["field/" + term[2]] += 1
            forms[f"distinct/{term[3]}"] += 1
    for row in expected({**response, "release": item["release"]})["allowedRows"]:
        for (term, _), cell in zip(columns, row):
            if term[0] != "aggregate" or term[1] == "count":
                continue
            if cell == [None]:
                forms["extremum/null"] += 1
            elif len(cell) > 1:
                forms["extremum/tied-representatives"] += 1


def required_forms(*_):
    return {"output/count", "output/key", "label/repeated", "group/repeated", "group/key", "group/global", "distinct/True",
            "distinct/False", "operator/min", "operator/max", "field/label", "field/alternate", "field/empty_value",
            "table/" + PRIMARY, "table/" + EMPTY, "filter/none", "filter/boolean", "filter/text-comparison", "filter/null-test",
            "extremum/null", "extremum/tied-representatives"}


def controls():
    tie = finite("mysql-9.1.0", "min", "label", False, "global", "tie")
    legacy = finite("mysql-5.7.44", "max", "label", True, "by-bucket", "all")
    checks = []
    for item in (tie, legacy):
        prediction = expected(item)
        good = {"status": "rows", "fields": prediction["fields"], "textMetadata": prediction["textMetadata"],
                "rows": [[choices[-1] for choices in group] for group in prediction["allowedRows"]]}
        check_observation(item, good, "registered extrema observation")
        rows = good["rows"]
        wrong = {**good, "rows": list(reversed(rows)) if ordered(item) else [["a   "]]}
        try:
            check_observation(item, wrong, "alter the stored tie representative or required row order")
        except Failure as error:
            require(error.signal == "meaning-mismatch", "mysql-varchar-extrema-wrong-control", error.detail)
        else:
            raise Failure("mysql-varchar-extrema-comparator-accepted")
        checks.append({"input": item, "expected": prediction, "matched": good, "mismatch": wrong})
    return checks


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/Aggregation/GroupedSql.php"
    native = "$renderer->expression($value->argument, $source)"
    result = "$value->operator->value . '(' . ($value->distinct ? 'DISTINCT ' : '') . $argument . ')'"
    ordinary = finite(tag, "min", "label", False, "global", "all")
    empty = finite(tag, "min", "label", False, "global", "empty")
    return [
        RenderFault("exchange-varchar-min-with-max", renderer, "$value->operator->value . '('", "'max('", ordinary, "meaning-mismatch"),
        RenderFault("read-adjacent-varchar-extremum-field", renderer, native,
                    "$renderer->expression(new \\SqlSemantics\\Platform\\MySql\\Ir\\From\\TextColumnReference(2), $source)",
                    ordinary, "meaning-mismatch"),
        RenderFault("replace-varchar-extremum-null", renderer, result,
                    '"COALESCE(" . (' + result + ') . ", \'\')"', empty, "meaning-mismatch"),
        RenderFault("change-varchar-extremum-collation", renderer, native, native + " . ' COLLATE utf8mb4_general_ci'",
                    finite(tag, "min", "label", False, "global", "tie"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
