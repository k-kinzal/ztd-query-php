"""Verify MySQL index options, native refusals and the total physical key limit."""

from copy import deepcopy
from itertools import product
import json
import sys

import run_server_mysql_create_btree_index as creation
import run_server_mysql_drop_index as removal
from mysql_index_policy_cases import (
    CAPACITY_SQL, IDENTITY_COUNTS, NEEDS_SPARE, ORDERED_LAYOUTS, analysis_request, capacity_model, generated,
    native_prediction, policy_model, policy_points, policy_sql,
)
from mysql_innodb_declaration_width_cases import OLD
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-index-policy"
NAME = "mysql-native-index-policy-evidence-v2.json"
CONTRACT = "1186f86f785635f4708135df8bbb83486c41b2cda22e05d856558f2936740a37"
CORRECTION = "970335f647fb8025448d372bd4caec4af6cdf5670bec326fe3193ad2726ba730"
BASE = "d7d21274000f3e881d4027516c2f1a9dd24474708a7632eaebb4068d6ef87ded"
OBSERVER = "mysql_index_policy_observer.php"
COMPARATOR_FAULTS = {"Ignore changes in retained physical keys.": "changed-retained-key"}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-native-index-policy-v1.json") == BASE,
            "mysql-index-policy-contract")
    spec = json.loads(path.read_text())
    baseline = json.loads((TOOL / "mysql-native-index-policy-v1.json").read_text())
    profiles = creation.specifications()[1]
    same(removal.specifications()[1], profiles)
    supplemental = TOOL / "mysql-native-index-policy-v2.json"
    require(digest(supplemental) == CORRECTION, "mysql-index-policy-correction")
    revision = json.loads(supplemental.read_text())
    same([name for name, _, _ in ORDERED_LAYOUTS], revision["finite"]["layoutEdges"])
    finite = baseline["finite"]
    finite["operationRoles"] = [*finite["operationRoles"], *revision["finite"]["addOperationRoles"]]
    same(finite, spec["finite"])
    same(baseline["exploration"], spec["exploration"])
    same(baseline["releases"], profiles["mysql"]["releases"])
    return spec, profiles


def finite_items(spec, tag, both_orders):
    finite = spec["finite"]
    points = list(policy_points(finite, both_orders))
    expected = spec["orderCoordinates"] if both_orders else spec["directCoordinates"]
    require(len(points) == len(set(points)) == expected, "mysql-index-policy-grid")
    yield from (policy_model(tag, point) for point in points)
    for count, primary in product(finite["priorKeyCounts"], finite["explicitPrimaryOwner"]):
        yield capacity_model(tag, count, primary)


def finite_ids(_, spec, tag):
    return covered(spec, tag, True)


def direct_finite_ids(_, spec, tag):
    return covered(spec, tag, False)


def covered(spec, tag, both_orders):
    points = {tuple(item["point"]) for item in finite_items(spec, tag, both_orders)
              if item["nativeError"] is None and not impossible_capacity(item)}
    expected = spec["analysisAdmitted"] if both_orders else spec["directAdmitted"]
    require(len(points) == expected, "mysql-index-policy-grid")
    return points


def impossible_capacity(item):
    return item["fixture"] == "capacity" and item["count"] == 0 and item["primary"]


def boundary(item, direct):
    tag = item["model"]["release"]
    exception = creation.DIAGNOSTIC
    if item["fixture"] == "capacity":
        message = (IDENTITY_COUNTS if impossible_capacity(item)
                   else "requires a settled native key count" if item["count"] == 65 and not direct
                   else NEEDS_SPARE)
    elif item["point"][2] == "INSTANT":
        if direct:
            exception, message = "ValueError", '"INSTANT" is not a valid backing value for enum'
        elif tag in OLD:
            exception, message = creation.SEMANTIC, "INSTANT is not an ALGORITHM the server knows."
        else:
            message = "accept DEFAULT, INPLACE or COPY algorithms"
    else:
        operation = "remove" if item["fixture"] == "drop" else "create"
        message = f"The algorithm and lock cannot {operation} this native InnoDB index."
    return {"exception": exception, "message": message}


def layout_edges(tag):
    for name, layout, role in ORDERED_LAYOUTS:
        item = policy_model(tag, (role, "persistent", "INPLACE", "NONE", "algorithm-first"), anchor=layout)
        yield name, item


def requests(engine, tag, spec):
    rejected = 0
    for direct in (False, True):
        for item in finite_items(spec, tag, not direct):
            impossible = impossible_capacity(item)
            if impossible and not direct:
                continue
            key = "/".join(item["point"])
            request = item["model"] if direct else analysis_request(item)
            route = "direct" if direct else "finite"
            if item["nativeError"] or impossible:
                rejected += 1
                yield f"negative/{route}/{key}", request, None if direct else item, boundary(item, direct)
            else:
                yield f"{route}/{key}", request, item, None
    for name, item in layout_edges(tag):
        for route, request in (("edge", analysis_request(item)), ("edge-direct", item["model"])):
            key = f"{route}/{name}"
            if item["nativeError"]:
                direct = route == "edge-direct"
                rejected += 1
                subject = None if direct else {**item, "rejectionCoordinate": name}
                yield "negative/" + key, request, subject, boundary(item, direct)
            else:
                yield key, request, item, None
    same(negative_count(spec, engine, tag), rejected)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, spec["finite"], seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analysis_request(item), item, None
            yield f"direct-seed/{seed}/{ordinal}", item["model"], item, None


def expect_sql(item, sql):
    if item["fixture"] == "capacity":
        alternatives = (CAPACITY_SQL,)
    else:
        alternatives = tuple(policy_sql(item, original) for original in (False, True))
    require(sql in alternatives, "index-policy-sql-mismatch", {"expectedSQL": alternatives, "sql": sql})


def analysis_finite_id(_, response, item, sql):
    same(analysis_request(item)["sql"], sql)
    return tuple(item["point"])


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return tuple(item["point"])


def rejected_finite_ids(spec, tag):
    finite = {"/".join(item["point"]) for item in finite_items(spec, tag, True) if item["nativeError"]}
    return finite | {name for name, item in layout_edges(tag) if item["nativeError"]}


def check_value(item, response):
    module = removal if item["fixture"] == "drop" else creation
    module.check_value(item, response)


def observer_request(item, sql):
    if item["fixture"] == "capacity":
        return {"fixture": "capacity", "sql": sql, "count": item["count"], "primary": item["primary"]}
    module = removal if item["fixture"] == "drop" else creation
    return {"fixture": item["fixture"], **module.observer_request(item, sql)}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = native_prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == expected["status"], "mysql-index-policy-observer-scope", witness)
    require({k: actual.get(k) for k in expected} == expected, "meaning-mismatch", witness)
    failure = item["nativeError"]
    if failure:
        require(actual.get("affected") is None and any(note[1] == failure[2] for note in actual["diagnostics"]),
                "meaning-mismatch", witness)
    else:
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == [],
                "meaning-mismatch", witness)
    try:
        expect_sql(item, sql)
    except Failure as error:
        raise Failure(error.signal, {**witness, **error.detail}) from error


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item.get("rejectionCoordinate", "/".join(item["point"]))


def sample_forms(forms, response, _, item):
    role, life, algorithm, lock, _order = item["point"]
    forms.update((f"operation/{role}", f"owner/{life}", f"algorithm/{algorithm}", f"lock/{lock}"))


def required_forms(_, _tag):
    return {"algorithm/DEFAULT", "algorithm/INPLACE", "algorithm/COPY",
            *(f"lock/{lock}" for lock in ("DEFAULT", "NONE", "SHARED", "EXCLUSIVE")),
            *(f"owner/{life}" for life in creation.LIFETIMES),
            *(f"operation/{role}" for role in ("create-ordinary", "create-unique", "drop-ordinary", "drop-unique", "drop-primary", "drop-clustered-unique"))}


def witness(tag):
    return policy_model(tag, ("create-unique", "persistent", "INPLACE", "NONE", "algorithm-first"))


def prepare(_, db):
    item = witness("mysql-5.6.51")
    outside = policy_sql(item, True) + "; SELECT 1"
    return {"outside": db.request(observer_request(item, outside)), "wrongFixture": db.request({"fixture": "unknown"})}


def check_preparation(_, _environment, receipt):
    same({"outside": {"status": "outside-observer-scope"}, "wrongFixture": {"status": "outside-observer-scope"}}, receipt)


def controls():
    item = witness("mysql-9.1.0")
    original = {**native_prediction(item), "affected": 0, "diagnostics": []}
    emitted = policy_sql(item, False)
    check_observation(item, original, emitted)
    changed = deepcopy(original)
    retained = next(row for row in changed["after"]["indexes"] if row[0] == "PRIMARY")
    retained[1] = 1
    try:
        check_observation(item, changed, emitted)
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"changed-retained-key": error.detail}
    raise Failure("mysql-index-policy-unobserved-key")


def check_fault_witness(item, witness):
    same(native_prediction(item), witness["expected"])
    same(["executed"], witness["observed"]["effect"])


def faults(_, tag):
    item = witness(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/Policy/IndexChangePolicy.php"
    return [
        RenderFault("erase-index-ddl-algorithm", path, "return $algorithm . $lock;", "return $lock;",
                    item, "index-policy-sql-mismatch", item["model"]),
        RenderFault("erase-index-ddl-none-lock", path, "return $algorithm . $lock;",
                    "return $algorithm . ($this->lock === IndexLock::None ? '' : $lock);",
                    item, "index-policy-sql-mismatch", item["model"]),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
