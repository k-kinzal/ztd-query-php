"""Compare release-sensitive MySQL DROP TABLE foreign-key effects."""

from copy import deepcopy
import json
import sys

import run_server_mysql_truncate as baseline
from mysql_drop_table_fk_cases import (
    FIRST, GRAPHS, LEGACY, SCOPES, STATUS, analyze, coordinate, declarations, explored, finite_coords, identifier, model, predicted, quote, sql,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-drop-table-foreign-keys"
NAME = "mysql-drop-table-foreign-keys-evidence-v1.json"
OBSERVER = "mysql_drop_table_fk_observer.php"
BASE = "e2b783218cbfd72a8413966423877fb2ce7251e4e01c71086d841eacaf76a439"
CONTRACT = "ed26401b577ea646c445b84464765565340420a06480e33172c2588c1306ecb3"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1"
MISSING = "DROP TABLE must include every referencing persistent child."
ORDER = "This MySQL release must drop a referencing child before its parent."
COMPARATOR_FAULTS = {"Hide partial native catalog effects after a refused legacy DROP.": "legacy-partial-effects-hidden"}


def specifications():
    fixed, current = TOOL / "mysql-drop-table-foreign-dependencies-v1.json", TOOL / NAME
    require(digest(fixed) == BASE and digest(current) == CONTRACT, "mysql-drop-table-foreign-keys-contract")
    spec, base = json.loads(current.read_text()), json.loads(fixed.read_text())
    same(base["finite"], spec["finite"])
    same(base["exploration"], spec["exploration"])
    same(base["releases"], spec["releases"])
    _, profiles = baseline.specifications()
    same(spec["releases"], profiles["mysql"]["releases"])
    return spec, profiles


def legal(item):
    return predicted(item)["effect"] == ["executed"]


def finite_ids(_, spec, tag):
    faces = finite_coords(spec["finite"])
    same(spec["finiteIdentities"], len(faces))
    resolvable = {point for point in faces if legal(coordinate(tag, point))}
    same(spec["legacyAdmitted" if tag in LEGACY else "modernAdmitted"], len(resolvable))
    return resolvable


def position(item):
    return {tuple(name[:2]): index for index,name in enumerate(item["drop"]["targets"]) if name[2] == "table"}


def expected_refusal(item, analysis):
    ranks = position(item)
    dependencies = item["drop"]["references"]
    if analysis:
        dependencies = sorted(dependencies, key=lambda edge: ranks[tuple(edge[1][:2])])
    for child, parent in dependencies:
        index = ranks.get(tuple(child[:2]))
        if index is None:
            return MISSING
        if item["release"] in LEGACY and index > ranks[tuple(parent[:2])]:
            return ORDER
    raise Failure("mysql-drop-foreign-keys-expected-invalid-instance", item)


def plan(spec, tag):
    for point in sorted(finite_coords(spec["finite"])):
        value = coordinate(tag, point)
        name = "/".join(point)
        if legal(value):
            yield from paired("finite", name, value, point)
        else:
            yield from refused("finite", name, value)

    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{ordinal}"
            if not legal(item):
                yield from refused("seed", name, item)
                item = model(tag, item["graph"], "parent-shadow", "parent-only", item["offset"], item["populated"], item["drop"]["ifExists"])
            yield from paired("seed", name, item)

    empty = model(tag, "single-edge", "persistent", "parent-only", populated=False, if_exists=True)
    yield from refused("edge", "empty-child-and-if-exists", empty)
    current = model(tag, "single-edge", "persistent", "child-only")
    yield "negative/open-persistent-catalog", {**analyze(current), "complete": False}, None, {
        "exception": baseline.DIAGNOSTIC, "message": "Persistent DROP TABLE needs a complete catalog",
    }
    wrong = deepcopy(current)
    wrong["drop"]["references"] = [[[*identifier(FIRST,"child"), "temporary-table"], [*identifier(FIRST,"parent"), "table"]]]
    yield "negative/temporary-foreign-key-role", wrong, None, {
        "exception": baseline.DIAGNOSTIC, "message": "A native foreign key relates two persistent tables.",
    }
    wrong = deepcopy(current)
    wrong["drop"]["references"] = [[[*identifier(FIRST,"child"), "table"], [*identifier(FIRST,"parent"), "table"]]]
    yield "negative/dependency-for-unselected-parent", wrong, None, {
        "exception": baseline.DIAGNOSTIC, "message": "A DROP TABLE dependency must name a selected persistent parent.",
    }


def paired(group, name, item, point=None):
    value = {"model": item, "point": list(point) if point is not None else None}
    direct = {"finite": "direct", "seed": "direct-seed"}[group]
    yield f"{group}/{name}", analyze(item), value, None
    yield f"{direct}/{name}", item, value, None


def refused(group, name, item):
    for source in ("direct","analysis"):
        via_analysis = source == "analysis"
        identifier = f"{group}/{name}/{source}"
        yield f"negative/{identifier}", analyze(item) if via_analysis else item, {"model": item, "rejectionId": identifier}, {
            "exception": baseline.DIAGNOSTIC, "message": expected_refusal(item, via_analysis),
        }


def requests(engine, tag, spec):
    cases = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(boundary is not None for _, _, _, boundary in cases))
    yield from cases


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, verdict in plan(spec, tag) if verdict is not None and item is not None}


def rejection_sql(item, _request):
    return sql(item["model"])


def observe_rejection(_, item, actual, command):
    require(not legal(item["model"]), "mysql-drop-table-foreign-keys-expected-native-failure")
    check_observation(item, actual, command)
    return item["rejectionId"]


def check_value(item, observed):
    model = item["model"]
    require(observed["status"] == "constructed", "mysql-drop-table-foreign-keys-constructed", observed)
    same(model["release"] + PROFILE, observed["profile"])
    intended = model["drop"]
    same({key: intended[key] for key in ("targets","ifExists","temporaryOnly")}, observed["mysqlDrop"])


def coordinate_seen(observed, item):
    model = item["model"]
    targets = observed["mysqlDrop"]["targets"]
    begins_parent = targets[0][1].endswith("_parent")
    order = ("parent-only" if begins_parent else "child-only") if len(targets) == 1 else ("parent-first" if begins_parent else "child-first")
    return model["graph"], model["scope"], order


def analysis_finite_id(_, observed, item, query):
    same(sql(item["model"], True), query)
    return coordinate_seen(observed, item)


def direct_finite_id(_, observed, item, request):
    same(item["model"], request)
    return coordinate_seen(observed, item)


def observer_request(item, command):
    model = item["model"]
    return {"sql": command, "declarations": declarations(model), "nodes": model["nodes"], "shadow": model["shadow"],
            "offset": model["offset"], "populated": model["populated"]}


def observation(raw):
    return raw


def check_observation(item, actual, command):
    expected = predicted(item["model"])
    witness = {"expected": expected, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "mysql-drop-table-foreign-keys-observer-scope", witness)
    require({field: actual[field] for field in expected} == expected, "meaning-mismatch", witness)
    native = expected["effect"]
    if native == ["executed"]:
        require(actual["diagnostics"] == [], "meaning-mismatch", witness)
    else:
        require(any(entry[:2] == ["Error", native[-1]] for entry in actual["diagnostics"]), "meaning-mismatch", witness)


def required_forms(_, _tag):
    return {*(f"graph/{graph}" for graph in GRAPHS), *(f"namespace/{scope}" for scope in SCOPES),
            "width/single", "width/multiple", "populated/True", "populated/False"}


def sample_forms(forms, actual, _, item):
    graph, scope, order = coordinate_seen(actual, item)
    width = "single" if len(actual["mysqlDrop"]["targets"]) == 1 else "multiple"
    forms.update((f"graph/{graph}", f"namespace/{scope}", f"order/{order}", f"width/{width}",
                  f"populated/{item['model']['populated']}"))


def outside():
    return ["TRUNCATE TABLE unrelated", "DROP TABLE unrelated", "DROP TABLE " + quote(identifier(FIRST,"child")) + "; SELECT 1"]


def prepare(_, db):
    item = {"model": model("mysql-9.1.0", "single-edge", "persistent", "child-first")}
    return {"outside": [db.request(observer_request(item, command)) for command in outside()]}


def check_preparation(_, _environment, yes):
    same({"outside": [{"status": "outside-observer-scope"}] * len(outside())}, yes)


def controls():
    item = {"model": model("mysql-5.6.51", "single-edge", "persistent", "parent-first")}
    expected = predicted(item["model"])
    healthy = {**expected, "diagnostics": [["Error", 1217, "foreign-key dependency"]]}
    check_observation(item, healthy, sql(item["model"]))
    wrong = {**healthy, "after": deepcopy(expected["before"])}
    try:
        check_observation(item, wrong, sql(item["model"]))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"legacy-partial-effects-hidden": error.detail}
    raise Failure("mysql-drop-table-foreign-keys-missed-comparator-control")


def check_fault_witness(item, witness):
    same(predicted(item["model"]), witness["expected"])
    same(["executed"], witness["observed"]["effect"])


def faults(_, tag):
    item = model(tag, "self-edge", "persistent", "parent-first")
    path = "sql-semantics-mysql/src/Ir/Schema/DropTable.php"
    return [RenderFault("omit-referencing-group-target", path, "implode(', ', $names)", "implode(', ', array_slice($names, 0, 1))",
                        {"model": item}, "meaning-mismatch", item)]


if __name__ == "__main__":
    main(sys.modules[__name__])
