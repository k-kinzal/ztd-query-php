"""Check the ordered optimizer flag assignments against native InnoDB effects."""

from copy import deepcopy
import json
import sys

import mysql_index_visibility_cases as laws
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-index-visibility"
OBSERVER = "mysql_index_visibility_observer.php"
NAME = "mysql-index-visibility-evidence-v1.json"
BASE = "f74710fe143d9ee77f60c211b0ff84331186b29a73263fc8e9c8bf34450cc090"
CONTRACT = "3dbff06f6c17906afef227f24115c70ff9549edfc7b4f1eeedf15ba0f78c837d"
PRIMARY = "Native index visibility requires a secondary key."
POLICY = "The algorithm and lock cannot change native index visibility on this table."
ALGORITHM = "Ordinary InnoDB index changes accept DEFAULT, INPLACE or COPY algorithms."
UNAVAILABLE = "Native index visibility requires MySQL 8.0 or later."
COMPARATOR_FAULTS = {
    "Report the old flag after a visibility edit.": "ignore-visibility",
    "Forget the unaltered relation's keys.": "ignore-control-catalog",
}


def specifications():
    frozen, current = TOOL / "mysql-index-visibility-v1.json", TOOL / NAME
    require(digest(frozen) == BASE and digest(current) == CONTRACT, "mysql-index-visibility-contract")
    spec, base = json.loads(current.read_text()), json.loads(frozen.read_text())
    _, profiles = native_profiles()
    same(base["finite"], spec["finite"])
    same(base["exploration"], spec["exploration"])
    same(base["releases"], spec["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    points = laws.finite_coords(spec["finite"])
    same(spec["finiteIdentities"], len(points))
    admitted = {coord for coord in points if laws.coordinate(tag, coord)["invalid"] is None}
    same(spec["admitted"], len(admitted))
    return admitted


def paired(axis, name, model):
    item = {"model": model}
    other = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}[axis]
    yield f"{axis}/{name}", laws.analyze(model), item, None
    yield f"{other}/{name}", laws.direct(model), item, None


def refused(axis, name, model):
    reason = model["invalid"]
    error = {
        "nonsecondary": PRIMARY, "implicit-primary": PRIMARY, "missing": "Native index visibility requires one resolved physical key.",
    }.get(reason, ALGORITHM if "instant" in reason else POLICY)
    routes = [("analysis", laws.analyze(model), DIAGNOSTIC, error)]
    if reason not in {"implicit-primary", "missing"}:
        exception, direct_error = ("ValueError", "is not a valid backing value") if "instant" in reason else (DIAGNOSTIC, error)
        routes.append(("direct", laws.direct(model), exception, direct_error))
    for source, request, exception, message in routes:
        identity = f"{axis}/{name}/{source}"
        yield f"negative/{identity}", request, {"model": model, "rejectionId": identity}, {"exception": exception, "message": message}


def plan(spec, tag):
    for point in sorted(laws.finite_coords(spec["finite"])):
        model = laws.coordinate(tag, point)
        name = "/".join(point)
        yield from paired("finite", name, model) if model["invalid"] is None else refused("finite", name, model)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from paired("seed", f"{seed}/{ordinal}", model)

    for family in ("prefix", "functional", "fulltext", "spatial"):
        model = laws.model(tag, "persistent", "default", "none", [(2, False), (1, False), (2, True), (0, False)], family, 12)
        yield from paired("authored", f"{family}-and-secondary-uniqueness", model)
    for case, changes, reason in (
        ("primary-visible", [(-1, True)], "nonsecondary"),
        ("primary-invisible", [(-1, False)], "nonsecondary"),
        ("implicit-primary-visible", [(1, True)], "implicit-primary"),
        ("implicit-primary-invisible", [(0, False), (1, False)], "implicit-primary"),
        ("missing-later-key", [(0, False), (-2, True)], "missing"),
    ):
        invalid = laws.model(tag, "persistent", "default", "default", changes, invalid=reason)
        yield from refused("authored", case, invalid)
    accepted = laws.model(tag, "persistent", "default", "default", [(0, False)])
    for release in ("mysql-5.6.51", "mysql-5.7.44"):
        yield f"negative/closed-release/{release}", laws.direct({**accepted, "release": release}), None, {
            "exception": DIAGNOSTIC, "message": UNAVAILABLE,
        }
    direct = laws.direct(accepted)
    yield "negative/empty-edit-list", {**direct, "mysqlIndexVisibility": {**direct["mysqlIndexVisibility"], "edits": []}}, None, {
        "exception": DIAGNOSTIC, "message": "Index visibility requires an ordered nonempty edit list.",
    }
    foreign_kind = laws.model(tag, "temporary", "default", "default", [(2, False)], "fulltext")
    yield "negative/fulltext-temporary-owner", laws.direct(foreign_kind), None, {
        "exception": DIAGNOSTIC, "message": "An InnoDB temporary table cannot own a FULLTEXT index.",
    }


def requests(engine, tag, spec):
    items = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(refusal is not None for _, _, _, refusal in items))
    yield from items


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, rejection in plan(spec, tag) if item is not None and rejection is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"])


def observe_rejection(_, item, actual, command):
    require(item["model"]["invalid"] is not None, "mysql-index-visibility-refusal-predicted-admitted")
    check_observation(item, actual, command)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual["status"] == "constructed", "mysql-index-visibility-construction", actual)
    same(model["release"] + laws.PROFILE, actual["profile"])
    same(laws.direct(model)["mysqlIndexVisibility"], actual["mysqlIndexVisibility"])


def observed_id(actual):
    owner, edits, policy = (actual["mysqlIndexVisibility"][name] for name in ("owner", "edits", "policy"))
    lifetime = "temporary" if owner[2] == "temporary-table" else "persistent"
    return lifetime, policy[0].lower(), policy[1].lower(), "visible" if edits[0][2] else "invisible"


def analysis_finite_id(_, actual, item, command):
    same(laws.sql(item["model"], True), command)
    return observed_id(actual)


def direct_finite_id(_, actual, item, request):
    same(laws.direct(item["model"]), request)
    return observed_id(actual)


def required_forms(_, _tag):
    return {"lifetime/persistent", "lifetime/temporary", "flag/visible", "flag/invisible", "repeats/True", "repeats/False",
            "policy/inplace", "policy/copy", "policy/default", "effective-first/changes", "effective-first/noop",
            "kind/ordinary", "kind/unique", "kind/fulltext", "kind/spatial", "namespace/control"}


def sample_forms(forms, actual, _, item):
    lifetime, algorithm, lock, flag = observed_id(actual)
    edits = actual["mysqlIndexVisibility"]["edits"]
    forms.update((f"lifetime/{lifetime}", f"policy/{algorithm}", f"lock/{lock}", f"width/{len(edits)}"))
    forms.update(f"kind/{kind}" for _, kind, _ in edits)
    forms.update("flag/visible" if visible else "flag/invisible" for _, _, visible in edits)
    forms[f"repeats/{len({edit[0] for edit in edits}) < len(edits)}"] += 1


def sample_native_forms(forms, actual, _, item):
    model = item["model"]
    prior = ("temporary" if model["lifetime"] == "temporary" else "persistent")
    name = laws.selected_name(model, model["edits"][0][0])
    term = next(key for key in actual["before"][prior] if key[0] == name)
    forms["effective-first/noop" if (term[-1] == "YES") == model["edits"][0][1] else "effective-first/changes"] += 1
    forms["namespace/control"] += int(actual["before"]["control"] == actual["after"]["control"])


def observer_request(item, command):
    return laws.observer_request(item["model"], command)


def observation(actual):
    return actual


def expected(item):
    model = item["model"]
    effect = laws.expected(model)
    affected = int(model["lifetime"] == "temporary" or model["policy"][0] == "copy") if model["invalid"] is None else None
    return {**effect, "affected": affected, "marker": 7, "active": False}


def check_observation(item, actual, sql):
    prediction = expected(item)
    detail = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual.get("status") == laws.STATUS, "mysql-index-visibility-unobserved", detail)
    same_fields = {key: actual.get(key) for key in prediction}
    require(same_fields == prediction, "meaning-mismatch", detail)
    require(actual["diagnostics"] == [] if prediction["effect"][0] == "executed" else
            any(note[:2] == ["Error", prediction["effect"][2]] for note in actual["diagnostics"]),
            "meaning-mismatch", detail)


def outside():
    return ["ALTER TABLE ir_visibility ALTER INDEX pv_A0 INVISIBLE",
            "ALTER TABLE `ir_fixture`.`ir_visibility` DROP INDEX `pv_A0`",
            "ALTER TABLE `ir_fixture`.`ir_visibility` ALTER INDEX `pv_A0` VISIBLE; SELECT 1"]


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0", "persistent", "default", "default", [(0, False)])}
    return {"outside": [db.request(observer_request(item, sql)) for sql in outside()]}


def check_preparation(_, _environment, preparation):
    same({"outside": [{"status": "outside-observer-scope"}] * len(outside())}, preparation)


def controls():
    item = {"model": laws.model("mysql-9.1.0", "persistent", "default", "default", [(0, False)])}
    command = laws.sql(item["model"])
    correct = {**expected(item), "diagnostics": []}
    check_observation(item, correct, command)
    wrong_flag = deepcopy(correct)
    target = laws.selected_name(item["model"], 0)
    next(entry for entry in wrong_flag["after"]["persistent"] if entry[0] == target)[-1] = "YES"
    missing_control = deepcopy(correct)
    missing_control["after"]["control"] = []
    evidence = {}
    for label, mutant in (("ignore-visibility", wrong_flag), ("ignore-control-catalog", missing_control)):
        try:
            check_observation(item, mutant, command)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            evidence[label] = error.detail
        else:
            raise Failure("mysql-index-visibility-comparator-missed", label)
    return evidence


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(["executed"], witness["observed"]["effect"])


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/Index/Visibility/SetIndexVisibility.php"
    repeat = laws.model(tag, "persistent", "default", "default", [(0, False), (0, True)])
    many = laws.model(tag, "temporary", "copy", "none", [(1, False), (0, False)])
    return [
        RenderFault("reverse-repeated-visibility-edits", path, "foreach ($this->edits as $edit)",
                    "foreach (array_reverse($this->edits) as $edit)", {"model": repeat}, "meaning-mismatch", laws.direct(repeat)),
        RenderFault("omit-leading-visibility-edit", path, "implode(', ', $clauses)",
                    "implode(', ', array_slice($clauses, 1))", {"model": many}, "meaning-mismatch", laws.direct(many)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
