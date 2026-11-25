"""Compare MySQL persistent view creation with an independent catalog and row model."""

from itertools import chain, product
import json
from random import Random
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as native_profiles
from run_server_join import main, probe
from run_server_mysql_external_views import COLLATION, DIAGNOSTICS
from server_join_cases import compare, slot, truth, value
from sqlite_domain import RenderFault

LABEL = "mysql-create-view"
OBSERVER = "mysql_create_view_observer.php"
CONTRACT_NAME = "mysql-create-view-v6.json"
CONTRACT = "8a0f7ec897c934550c0b307681a75dc46ba648d3515198ac65d9f0d4d2b3a2b7"
BASE_CONTRACT = "7465a47332cd41088ffc7ed5bc997353a35a553e8aade2c51e8392b09e904d1c"
COMPLETE_SEEDS = True
AXES = ("algorithm", "security", "policy", "body", "fixture")
ALGORITHMS = {"automatic": "UNDEFINED", "merge": "MERGE", "temporary": "TEMPTABLE"}
LITERALS = {"unit-plain": None, "unit-retained": ["boolean", True], "unit-empty": ["boolean", False]}
DISTINCT_LITERALS = {"unit-distinct": None, "unit-distinct-empty": ["boolean", False]}
COMPARATOR_FAULTS = {
    "Ignore a missing native view fallback warning while still comparing the installed catalog": "missing-fallback",
    "Ignore an incorrect affected row count after a permitted write through an otherwise matching view": "incorrect-write-count",
}
SCHEMA, MAIN, DECOY, BASE = "ir_fixture", "ir_cv_main", "ir_cv_decoy", "ir_cv_source"
TARGET = f"{SCHEMA}.{MAIN}"
ORIGIN = f"{SCHEMA}.{BASE}"
HEADING = ("id", "label")
TYPES = {"slot": ["int", None, None, "YES"], "text-slot": ["varchar", 8, COLLATION, "YES"],
         "integer": ["int", None, None, "NO"]}
DATA = [(1, "alpha"), (2, "beta"), (2, "beta"), (3, None), (None, "orphan")]
DECLARATIONS = [f"CREATE TABLE {ORIGIN} (id INT, label VARCHAR(8) COLLATE {COLLATION})"]
INVALID = DIAGNOSTICS + "InvalidConstruction"
MERGE_WARNING = ["Warning", 1354, "View merge algorithm can't be used here for now (assumed undefined algorithm)"]
WRITE_ROWS = [(0, "low"), (None, "unknown"), (5, "high")]
VISIBLE_ID, REPLACEMENT_ID = 2, 0
UNUPDATABLE = "A view check option requires a mergeable base-table projection."
CHECK_REJECTION = ["db-error", "HY000", 1368]
WRITE_REJECTION = ["db-error", "HY000", 1369]
CATALOG_TERMS = {
    "base-constant": [(["integer", "42"], "value")],
    "base-mixed": [(slot(0), "id"), (["text-slot", 1, 0], "label"), (["integer", "42"], "value")],
    "base-duplicate": [(slot(0), "id"), (slot(0), "id_copy")],
}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-create-view-v1.json") == BASE_CONTRACT, "mysql-create-view-contract-mismatch")
    contract = json.loads(path.read_text())
    prior = contract["versionedFrom"]
    same(prior["sha256"], digest(TOOL / prior["path"]))
    same([list(row) for row in WRITE_ROWS], contract["writeProbes"]["rows"])
    same({"visibleId": VISIBLE_ID, "candidateId": REPLACEMENT_ID}, contract["writeProbes"]["update"])
    return contract, {"mysql": native_profiles()[1]["mysql"]}


def source(identity=BASE, relation="table"):
    return {"schema": SCHEMA, "table": identity, "columns": list(HEADING),
            "columnTypes": ["int", ["varchar", 8, COLLATION]], **({"relationKind": relation} if relation != "table" else {})}


def query(branch=None, columns=None, where=None):
    return {"source": branch, "columns": list(columns), "where": where, "distinct": False, "orderBy": [], "limit": None}


def scan(filtered=False, reversed_fields=False, threshold=1):
    columns = [[slot(0), "id"], [["text-slot", 1, 0], "label"]]
    return query(source(), columns[::-1] if reversed_fields else columns,
                 compare(">", slot(0), ["integer", str(threshold)]) if filtered else None)


def constant(number=42, *, where=None):
    return query(None, [[["integer", str(number)], "value"]], where)


def case(tag, body, fields=("id", "label"), policy="require-new", algorithm="UNDEFINED", security="DEFINER", existing=False):
    return {"dialect": "mysql", "release": tag, "existing": existing,
            "createView": {"target": [SCHEMA, MAIN], "query": body, "columns": list(fields),
                           "policy": policy, "algorithm": algorithm, "security": security}}


def finite(tag, algorithm, security, policy, body, fixture):
    literal_forms = {**LITERALS, **DISTINCT_LITERALS}
    literal = body in literal_forms
    query = constant(where=literal_forms[body]) if literal else scan(body.endswith("filtered"))
    query["distinct"] = body in DISTINCT_LITERALS or body.startswith("base-distinct-")
    if body in CATALOG_TERMS:
        query = {**scan(True), "columns": [[term, name] for term, name in CATALOG_TERMS[body]]}
    if body == "base-false":
        query["where"] = ["boolean", False]
    fields = tuple(name for _, name in query["columns"])
    return case(tag, query, fields, policy, ALGORITHMS[algorithm], security.upper(),
                fixture == "existing-view")


def finite_grid(spec, tag):
    coordinates = {axis: spec["finite"][axis] for axis in AXES}
    coordinates["policy"] = coordinates["policy"] + spec["finite"]["policyAdditionalByRelease"].get(tag, [])
    return product(*(coordinates[axis] for axis in AXES))


def check_case(item, mode):
    view = item["createView"]
    check = "CASCADED" if mode == "IMPLICIT" else mode
    names = {term[0]: name for (term, _), name in zip(view["query"]["columns"], view["columns"])}
    writes = {"writeNames": [names["slot"], names["text-slot"]]} if (
        len(view["columns"]) == 2 and names.keys() == {"slot", "text-slot"}) else {}
    return {**item, "createView": {**view, **({"checkOption": check} if check != "NONE" else {})},
            "checkObservation": True, **writes}


def checked_grid(spec, tag, analysis):
    settings = spec["checkFinite"]
    coordinates = {axis: settings[axis] for axis in (*AXES, "mode")}
    coordinates["policy"] = coordinates["policy"] + settings["policyAdditionalByRelease"].get(tag, [])
    if not analysis:
        coordinates["mode"] = [mode for mode in coordinates["mode"] if mode != "IMPLICIT"]
    return product(*(coordinates[axis] for axis in (*AXES, "mode")))


def checked_catalog_grid(spec, analysis):
    settings = spec["checkCatalogFinite"]
    modes = [mode for mode in settings["mode"] if analysis or mode != "IMPLICIT"]
    for algorithm, body, mode in product(settings["algorithm"], settings["body"], modes):
        yield (algorithm, settings["security"], settings["policy"], body, settings["fixture"], mode)


def identities(spec, tag, analysis):
    grid = list(finite_grid(spec, tag))
    policies = ("require-new", "replace", "preserve") if tag == "mysql-9.1.0" else ("require-new", "replace")
    independent = {(algorithm, security, policy, body, fixture) for fixture in ("absent", "existing-view")
                   for body in ("base-all", "base-filtered", "base-distinct-all", "base-distinct-filtered", *LITERALS, *DISTINCT_LITERALS)
                   for policy in policies
                   for security in ("definer", "invoker") for algorithm in ALGORITHMS}
    require(len(grid) == len(independent) == spec["finite"]["identities"].get(tag, spec["finite"]["identities"]["standard"])
            and set(grid) == independent, "mysql-create-view-finite-size")
    direction = "analysis" if analysis else "direct"
    cap = spec["checkFinite"]["identities"][direction]
    modes = ("NONE", "LOCAL", "CASCADED", "IMPLICIT") if analysis else ("NONE", "LOCAL", "CASCADED")
    expected = {(algorithm, security, policy, body, fixture, mode)
                for body in ("base-all", "base-filtered", "base-false") for mode in modes for security in ("definer", "invoker")
                for fixture in ("absent", "existing-view") for algorithm in ("automatic", "merge") for policy in policies}
    checked = list(checked_grid(spec, tag, analysis))
    require(len(checked) == len(expected) == cap.get(tag, cap["standard"]) and set(checked) == expected,
            "mysql-create-view-check-finite-size", direction)
    catalog = {(algorithm, "definer", "require-new", body, "absent", mode)
               for body in ("base-constant", "base-mixed", "base-duplicate") for mode in modes for algorithm in ("merge", "automatic")}
    generated_catalog = list(checked_catalog_grid(spec, analysis))
    require(len(generated_catalog) == len(catalog) == spec["checkCatalogFinite"]["identities"][direction]
            and set(generated_catalog) == catalog, "mysql-create-view-check-catalog-size", direction)
    return independent | expected | catalog


def finite_ids(_, spec, tag):
    return identities(spec, tag, True)


def direct_finite_ids(_, spec, tag):
    return identities(spec, tag, False)


def finite_id(actual, item, implicit=False):
    view = actual["createView"]
    query = view["query"]
    if query["source"] is None:
        forms = DISTINCT_LITERALS if query["distinct"] else LITERALS
        observed = [name for name, predicate in forms.items() if predicate == query["where"]]
        require(len(observed) == 1, "mysql-create-view-literal-coordinate", query)
        body = observed[0]
    elif tuple(term[0] for term, _ in query["columns"]) == ("integer",):
        body = "base-constant"
    elif tuple(term[0] for term, _ in query["columns"]) == ("slot", "text-slot", "integer"):
        body = "base-mixed"
    elif tuple(term[0] for term, _ in query["columns"]) == ("slot", "slot"):
        body = "base-duplicate"
    elif query["where"] == ["boolean", False]:
        body = "base-false"
    else:
        body = ("base-distinct-" if query["distinct"] else "base-") + ("filtered" if query["where"] else "all")
    identity = (next(name for name, role in ALGORITHMS.items() if role == view["algorithm"]), view["security"].lower(),
                view["policy"], body, "existing-view" if item["existing"] else "absent")
    if not item.get("checkObservation", False):
        return identity
    mode = view.get("checkOption", "NONE")
    if implicit:
        same("CASCADED", mode)
        return (*identity, "IMPLICIT")
    return (*identity, mode)


def finite_sql(item, implicit=False):
    view = item["createView"]
    query = view["query"]
    start = "CREATE OR REPLACE" if view["policy"] == "replace" else "CREATE"
    preserve = "IF NOT EXISTS " if view["policy"] == "preserve" else ""
    names = ", ".join(view["columns"])
    projection = "SELECT DISTINCT " if query["distinct"] else "SELECT "
    if query["source"] is not None:
        columns = []
        for term, name in query["columns"]:
            source = {"slot": "id", "text-slot": "label", "integer": "42"}[term[0]]
            columns.append(source if source == name else f"{source} AS {name}")
        body = projection + ", ".join(columns) + f" FROM {ORIGIN}"
        if query["where"] is not None:
            body += " WHERE FALSE" if query["where"] == ["boolean", False] else " WHERE id > 1"
    else:
        body = projection + "42 AS value"
        if query["where"] is not None:
            body += " FROM DUAL WHERE " + ("TRUE" if query["where"][1] else "FALSE")
    guard = " WITH CHECK OPTION" if implicit else (
        "" if "checkOption" not in view else f" WITH {view['checkOption']} CHECK OPTION")
    return f"{start} ALGORITHM={view['algorithm']} SQL SECURITY {view['security']} VIEW {preserve}{TARGET} ({names}) AS {body}{guard}"


def analysis_finite_id(engine, actual, item, sql):
    implicit = sql.endswith(" WITH CHECK OPTION")
    same(finite_sql(item, implicit), sql)
    return finite_id(actual, item, implicit)


def direct_finite_id(engine, actual, item, request):
    same(item, request)
    return finite_id(actual, item)


def request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def analyzed(tag):
    statement = f"SELECT id,label FROM {ORIGIN}"
    yield f"CREATE VIEW {TARGET} AS {statement}", case(tag, scan())
    yield f"CREATE VIEW {TARGET} (item,detail) AS {statement}", case(tag, scan(), ("item", "detail"))
    yield f"CREATE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW {TARGET} AS {statement}", case(tag, scan(), algorithm="MERGE")
    yield f"CREATE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW {TARGET} AS {statement}", case(
        tag, scan(), algorithm="TEMPTABLE", security="INVOKER")
    yield f"CREATE VIEW {TARGET} AS {statement} WHERE id > 1", case(tag, scan(True))
    yield f"CREATE VIEW {TARGET} AS SELECT 42 AS value", case(tag, constant(), ("value",))
    yield f"CREATE OR REPLACE VIEW {TARGET} (item,detail) AS {statement}", case(
        tag, scan(), ("item", "detail"), policy="replace", existing=True)
    yield f"CREATE VIEW {TARGET} AS SELECT 42 AS value FROM DUAL WHERE FALSE", case(
        tag, constant(where=["boolean", False]), ("value",))


def optional(tag):
    sql = f"CREATE VIEW IF NOT EXISTS {TARGET} AS SELECT id,label FROM {ORIGIN}"
    for existing in (True, False):
        item = case(tag, scan(), policy="preserve", existing=existing)
        yield f"optional/{int(existing)}", request(tag, sql), item, None


def negatives(tag):
    body = scan()
    for item, message in (
        (case(tag, body, ("label",)), "The view heading must match the query width."),
        (case(tag, body, ("label", "Label")), "The view heading cannot repeat a column name."),
        (case(tag, query(None, [[["parameter", 1], "v"]]), ("v",)), "A checked view result must read a native field or a literal without parameters."),
        (case(tag, {**body, "source": source(MAIN)}), "A view cannot read its own target identity."),
        (case(tag, {**body, "source": source(relation="view")}), "A checked view reads one persistent base table or an independent literal projection."),
        (request(tag, f"CREATE DEFINER=CURRENT_USER VIEW {TARGET} AS SELECT id,label FROM {ORIGIN}"),
         "CREATE VIEW needs an implicit definer and one admitted creation policy."),
        (case(tag, {**body, "release": "mysql-9.0.1"}, policy="preserve"), "CREATE VIEW IF NOT EXISTS requires MySQL 9.1."),
    ):
        yield item, {"exception": INVALID, "message": message}


def check_negatives(tag):
    body = scan(True)
    for index, (query_, columns, algorithm, mode) in enumerate((
        (constant(), ("value",), "MERGE", "CASCADED"),
        ({**body, "distinct": True}, HEADING, "UNDEFINED", "LOCAL"),
        (body, HEADING, "TEMPTABLE", "CASCADED"),
        ({**body, "distinct": True}, HEADING, "MERGE", "CASCADED"),
    )):
        item = case(tag, query_, columns, algorithm=algorithm)
        item["createView"]["checkOption"] = mode
        input_ = request(tag, finite_sql(item)) if index == 3 else item
        yield (input_, item, {"exception": INVALID, "message": UNUPDATABLE})


def rejection_sql(item, _):
    return finite_sql(item)


def observe_rejection(spec, item, native, sql):
    require(native["status"] == "view-definition" and native["result"] == CHECK_REJECTION,
            "mysql-create-view-guard-native-rejection", {"sql": sql, "native": native})
    before, after = native["initial"]["views"], native["after"]["views"]
    same(before, after)
    view = item["createView"]
    query_ = view["query"]
    body = "independent" if query_["source"] is None else ("distinct" if query_["distinct"] else "base")
    identity = (body, view["algorithm"], view["checkOption"])
    require(list(identity) in spec["nativeNegative"], "unexpected-mysql-check-rejection", identity)
    codes = [[d[0], d[1]] for d in native["diagnostics"]]
    expected = [["Warning", 1354]] if body != "base" and view["algorithm"] == "MERGE" else []
    same([*expected, ["Error", 1368]], codes)
    return identity


def rejected_finite_ids(spec, _):
    return [tuple(item) for item in spec["nativeNegative"]]


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        body = scan(index % 2 == 0, index % 3 == 0, rng.randrange(1, 3))
        labels = rng.choice((("item", "field"), ("code", "note"), ("whitespace label", "position")))
        yield case(tag, body, labels, rng.choice(("require-new", "replace")), rng.choice(tuple(ALGORITHMS.values())),
                   rng.choice(("DEFINER", "INVOKER")), bool(rng.getrandbits(1)))


def seeded_literal(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        guard = LITERALS[tuple(LITERALS)[index % len(LITERALS)]]
        body = constant(rng.randint(-99, 99), where=guard)
        yield case(tag, body, (rng.choice(("counter", "recent", "projected value")),),
                   rng.choice(("require-new", "replace")), rng.choice(tuple(ALGORITHMS.values())),
                   rng.choice(("DEFINER", "INVOKER")), bool(rng.getrandbits(1)))


def seeded_distinct(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        literal = index % 2 == 0
        body = constant(rng.randint(-99, 99), where=rng.choice(tuple(DISTINCT_LITERALS.values()))) if literal else (
            scan(bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), rng.randrange(1, 3)))
        names = ("answer",) if literal else ("distinct_id", "distinct_label")
        yield case(tag, {**body, "distinct": True}, names, rng.choice(("require-new", "replace")),
                   rng.choice(tuple(ALGORITHMS.values())), rng.choice(("DEFINER", "INVOKER")), bool(rng.getrandbits(1)))


def seeded_check(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        body = scan(bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), rng.randrange(1, 3))
        names = rng.choice((("first", "second"), ("result_a", "result_b"), ("value", "caption")))
        item = case(tag, body, names, rng.choice(("require-new", "replace")), rng.choice(("UNDEFINED", "MERGE")),
                    rng.choice(("DEFINER", "INVOKER")), bool(rng.getrandbits(1)))
        yield check_case(item, ("NONE", "LOCAL", "CASCADED")[index % 3])


def requests(_, tag, spec):
    offset = 0
    for index, dimensions in enumerate(finite_grid(spec, tag)):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", request(tag, finite_sql(item)), item, None
        yield f"direct/{index}", item, item, None
        offset = index + 1
    for route, analysis in (("finite", True), ("direct", False)):
        dimensions = chain(checked_grid(spec, tag, analysis), checked_catalog_grid(spec, analysis))
        for index, (*body, mode) in enumerate(dimensions, offset):
            item = check_case(finite(tag, *body), mode)
            if analysis and body[3] in {"base-constant", "base-duplicate"}:
                # Only the resolved id field participates in these two parsed projections.
                item["createView"]["query"]["source"] = {"schema": SCHEMA, "table": BASE, "columns": ["id"]}
            incoming = request(tag, finite_sql(item, mode == "IMPLICIT")) if analysis else item
            yield f"{route}/{index}", incoming, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "mysql-create-view-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    if tag in spec["optionalNativeForms"]:
        yield from optional(tag)
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
        plain = spec["exploration"]["plainDirectCasesPerSeed"]
        checked = spec["exploration"]["checkCasesPerSeed"]
        distinct = spec["exploration"]["directCasesPerSeed"] - plain - checked
        for index, item in enumerate(seeded_literal(tag, seed + 1, plain)):
            yield f"direct-seed/{seed}/{index}", item, item, None
        for index, item in enumerate(seeded_distinct(tag, seed + 2, distinct), plain):
            yield f"direct-seed/{seed}/{index}", item, item, None
        for index, item in enumerate(seeded_check(tag, seed + 3, checked), plain + distinct):
            yield f"direct-seed/{seed}/{index}", item, item, None
    rejected = chain(((item, None, reason) for item, reason in negatives(tag)), check_negatives(tag))
    for index, (incoming, item, reason) in enumerate(rejected):
        yield f"negative/{index}", incoming, item, reason


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"), "mysql-create-view-rejected", reply)
    require(reply["createView"] == item["createView"], "mysql-create-view-graph", {"expected": item, "observed": reply, "sql": reply["sql"]})


def relation(body, names, algorithm, security, data, check="NONE"):
    table = body["source"]
    rows = [(tuple(row),) for row in data] if table is not None else [()]
    fields = [[label, *TYPES[term[0]]] for (term, _), label in zip(body["columns"], names)]
    contents = [[value(term, row, "mysql") for term, _ in body["columns"]] for row in rows if truth(body["where"], row, "mysql")]
    if body["distinct"]:
        contents = list({canonical(row): row for row in contents}.values())
    reads_field = any(term[0] in {"slot", "text-slot"} for term, _ in body["columns"])
    updatable = table is not None and algorithm != "TEMPTABLE" and not body["distinct"] and reads_field
    return {"algorithm": algorithm, "security": security, "updatable": "YES" if updatable else "NO",
            "check": check, "columns": fields, "rows": sorted(contents, key=canonical)}


def fixture(existing, data, marker):
    views = {DECOY: relation(constant(71), ["decoy"], "UNDEFINED", "DEFINER", data)}
    if existing:
        saved = query(source(), [[slot(0), "id"]], compare(">", slot(0), ["integer", "2"]))
        views[MAIN] = relation(saved, ["original"], "TEMPTABLE", "INVOKER", data)
    return {"views": views, "marker": marker}


def write_effects(view, stored):
    body, heading = view["query"], view["columns"]
    policy = view.get("checkOption", "NONE")
    data = list(DATA)
    effects = []

    def visible(row):
        return truth(body["where"], (row,), "mysql")

    def record(result):
        catalog = relation(body, heading, stored, view["security"], data, policy)
        effects.append({"result": result, "base": sorted(map(list, data), key=canonical), "view": catalog["rows"]})

    for row in WRITE_ROWS:
        if policy == "NONE" or visible(row):
            data.append(row)
            record(["affected", 1])
        else:
            record(WRITE_REJECTION)
    affected = [position for position, row in enumerate(data) if row[0] == VISIBLE_ID and visible(row)]
    replacements = [(REPLACEMENT_ID, data[position][1]) for position in affected]
    if policy != "NONE" and not all(visible(row) for row in replacements):
        record(WRITE_REJECTION)
    else:
        for position, replacement in zip(affected, replacements):
            data[position] = replacement
        record(["affected", len(affected)])
    return effects


def prediction(item):
    view, existing = item["createView"], item["existing"]
    update = [(key, "changed" if key == 2 else label) for key, label in DATA] + [(4, "new")]
    collision = existing and view["policy"] != "replace"
    failed = collision and view["policy"] == "require-new"
    fallback = view["algorithm"] == "MERGE" and (view["query"]["source"] is None or view["query"]["distinct"])
    stored = "UNDEFINED" if fallback else view["algorithm"]
    after, later = (fixture(existing, data, [0, 7]) for data in (DATA, update))
    if not collision:
        for snapshot, data in ((after, DATA), (later, update)):
            snapshot["views"][MAIN] = relation(view["query"], view["columns"], stored, view["security"], data,
                                              view.get("checkOption", "NONE"))
    diagnostics = [MERGE_WARNING] if fallback else []
    if collision:
        diagnostics.append(["Error" if failed else "Note", 1050, "Table 'ir_cv_main' already exists"])
    writes = {}
    if "writeNames" in item:
        writes["writeEffects"] = "definition-not-installed" if collision else write_effects(view, stored)
    return {"status": "view-definition", "result": ["db-error", "42S01", 1050] if failed else ["created"], "diagnostics": diagnostics,
            "active": False, "initial": fixture(existing, DATA, [0]), "after": after, "afterRollback": after, "afterSourceChange": later,
            **writes}


def observer_request(item, sql):
    return {"sql": sql, "existing": item["existing"], **({"writeNames": item["writeNames"]} if "writeNames" in item else {})}


def observation(raw):
    if raw["status"] != "view-definition":
        return raw
    result = dict(raw)
    for phase in ("initial", "after", "afterRollback", "afterSourceChange"):
        state = raw[phase]
        result[phase] = {**state, "views": {name: {**view, "rows": sorted(view["rows"], key=canonical)}
                                           for name, view in state["views"].items()}}
    if isinstance(raw.get("writeEffects"), list):
        result["writeEffects"] = [
            {**effect, "base": sorted(effect["base"], key=canonical), "view": sorted(effect["view"], key=canonical)}
            for effect in raw["writeEffects"]
        ]
    return result


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, prepared):
    same(environment["initial"], prepared["initial"])
    same(fixture(True, DATA, [0]), prepared["initial"])


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "view-definition", "mysql-create-view-observer-scope", witness)
    require(expected == actual, "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    value_ = reply["createView"]
    for attribute in ("policy", "algorithm", "security"):
        forms[attribute + "/" + value_[attribute]] += 1
    query_ = value_["query"]
    forms["filter/" + ("present" if query_["where"] else "absent")] += 1
    forms["slot/" + query_["columns"][0][0][0]] += 1
    forms["fixture/" + str(item["existing"])] += 1
    forms["distinct/" + str(query_["distinct"])] += 1
    if query_["distinct"]:
        forms["distinct-source/" + ("literal" if query_["source"] is None else "physical")] += 1
    if query_["source"] is None:
        forms["literal/" + str(query_["where"] == ["boolean", False])] += 1
        forms["literal-algorithm/" + value_["algorithm"]] += 1
    if item.get("checkObservation", False):
        forms["check/" + value_.get("checkOption", "NONE")] += 1
        forms["check-id-label-position/" + str(item["writeNames"] == value_["columns"])] += 1


def sample_native_forms(forms, actual, *_):
    writes = actual.get("writeEffects")
    if isinstance(writes, list):
        for effect in writes:
            result = effect["result"]
            forms["write/" + result[0] + "/" + str(result[-1])] += 1


def required_forms(*_):
    return {"filter/present", "filter/absent", "slot/slot", "slot/text-slot", "fixture/True", "fixture/False",
            "policy/require-new", "policy/replace", "security/DEFINER", "security/INVOKER",
            "slot/integer", "literal/False", "literal/True", "distinct/False", "distinct/True",
            "distinct-source/literal", "distinct-source/physical",
            "check/NONE", "check/LOCAL", "check/CASCADED", "check-id-label-position/True", "check-id-label-position/False",
            "write/affected/1", "write/affected/2", "write/db-error/1369",
            *("algorithm/" + role for role in ALGORITHMS.values()),
            *("literal-algorithm/" + role for role in ALGORITHMS.values())}


def controls():
    item = finite("mysql-9.1.0", "merge", "invoker", "replace", "base-all", "existing-view")
    healthy = prediction(item)
    check_observation(item, healthy, "view definition control")
    wrong = prediction({**item, "createView": {**item["createView"], "security": "DEFINER"}})
    fallback = finite("mysql-9.1.0", "merge", "invoker", "replace", "unit-empty", "existing-view")
    merged = prediction(fallback)
    check_observation(fallback, merged, "native merge fallback control")
    checked = check_case(finite("mysql-9.1.0", "automatic", "invoker", "require-new", "base-filtered", "absent"), "LOCAL")
    effects = prediction(checked)
    check_observation(checked, effects, "native view write control")
    wrong_count = list(effects["writeEffects"])
    wrong_count[2] = {**wrong_count[2], "result": ["affected", 0]}
    observed = {}
    for key, case_, mismatch in (
        ("wrong-security", item, wrong),
        ("missing-fallback", fallback, {**merged, "diagnostics": []}),
        ("incorrect-write-count", checked, {**effects, "writeEffects": wrong_count}),
    ):
        try:
            check_observation(case_, mismatch, key)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            observed[key] = {"input": case_, **error.detail}
        else:
            raise Failure("mysql-create-view-comparator-accepted", key)
    return observed


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-create-view-mutant-response", reply)
    raise Failure("mysql-create-view-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-create-view-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/View/CreateView.php"
    model = finite(tag, "merge", "invoker", "replace", "base-all", "existing-view")
    fallback = finite(tag, "merge", "invoker", "replace", "unit-empty", "existing-view")
    unique = finite(tag, "automatic", "invoker", "replace", "base-distinct-all", "existing-view")
    invalid, reason = list(negatives(tag))[1]
    invalid_guard, _, guard_reason = next(check_negatives(tag))
    checked = check_case(finite(tag, "merge", "invoker", "replace", "base-filtered", "existing-view"), "LOCAL")
    return [
        RenderFault("replace-another-view-definition", path, "$this->name->quoted()", "'`ir_fixture`.`ir_cv_decoy`'", model, "meaning-mismatch"),
        RenderFault("swap-view-heading-positions", path, "implode(', ', $names)", "implode(', ', array_reverse($names))", model, "meaning-mismatch"),
        RenderFault("drop-view-replacement-policy", path, "'CREATE OR REPLACE' : 'CREATE'", "'CREATE' : 'CREATE'", model, "meaning-mismatch"),
        RenderFault("switch-view-security-mode", path, "$this->security->value", "'DEFINER'", model, "meaning-mismatch"),
        RenderFault("allow-repeated-view-heading-name", path, "!isset($seen[$key]),", "true,",
                    {"boundary": reason}, "mysql-create-view-invalid-published", invalid),
        RenderFault("erase-requested-view-merge-fallback", path, "$this->algorithm->value", "$this->installedAlgorithm->value",
                    fallback, "meaning-mismatch"),
        RenderFault("erase-distinct-from-view-body", "sql-semantics-mysql/src/Ir/SelectSql.php",
                    "($select->distinct ? 'SELECT DISTINCT ' : 'SELECT ')", "'SELECT '",
                    unique, "meaning-mismatch"),
        RenderFault("allow-check-option-on-nonmergeable-view", path,
                    "$checkOption === ViewCheckOption::None || ($query->source !== null && !$query->distinct && $algorithm !== ViewAlgorithm::TempTable),",
                    "true,", {"boundary": guard_reason}, "mysql-create-view-invalid-published", invalid_guard),
        RenderFault("drop-checked-view-guard", path, "$this->checkOption === ViewCheckOption::None ? '' : ' WITH ' . $this->checkOption->value . ' CHECK OPTION'",
                    "''", checked, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
