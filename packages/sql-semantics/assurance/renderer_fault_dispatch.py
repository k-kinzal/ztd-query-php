"""Record fault requests before execution and check their independent replay linkage."""

import json

from evidence_transcript import canonical
from run import TOOL, digest, require

CONTRACT_NAME = "renderer-fault-dispatch-v1.json"
CONTRACT = "7429c4e0fd439f456b37197b48b00d45d96935791cf183d18785ece609518e1a"
PLAN_NAME = "fault-dispatch.json"
FAILURE_NAME = "fault-dispatch-failure.json"


def plan(fixtures, script):
    require(digest(TOOL / CONTRACT_NAME) == CONTRACT, "renderer-fault-dispatch-contract")
    records = [{
        "case": fault.name,
        "file": fault.path,
        "input": fault.item,
        "request": fault.item if fault.request is None else fault.request,
        "expected": fault.signal,
    } for fault in fixtures]
    require(len(records) == len({record["case"] for record in records}), "renderer-fault-dispatch-duplicate-case")
    return {"contract": CONTRACT, "worker": script, "faults": records}


def receipt(plan_digest, entry):
    return {"planSha256": plan_digest, "request": entry["request"]}


def verify(directory, fixtures, script, records):
    expected = plan(fixtures, script)
    path = (directory / PLAN_NAME).resolve()
    require(path.parent == directory.resolve() and path.is_file(), "renderer-fault-dispatch-plan-missing", str(path))
    require(canonical(json.loads(path.read_text())) == canonical(expected), "renderer-fault-dispatch-plan-mismatch", str(path))
    require(not (directory / FAILURE_NAME).exists(), "renderer-fault-dispatch-incomplete", str(directory))
    checksum = digest(path)
    by_case = {record["case"]: record for record in records}
    faults = expected["faults"]
    require(len(by_case) == len(records) and by_case.keys() == {entry["case"] for entry in faults},
            "renderer-fault-dispatch-receipt-set", str(directory))
    for entry in faults:
        actual = by_case[entry["case"]]
        require(canonical(actual.get("dispatch")) == canonical(receipt(checksum, entry)),
                "renderer-fault-dispatch-receipt-mismatch", entry["case"])
    return {"path": PLAN_NAME, "sha256": checksum, "contract": CONTRACT}
