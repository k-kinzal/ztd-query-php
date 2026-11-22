"""Preserve the primary failure and record a separately verified smaller witness."""

import json

from run import Failure, TOOL, digest, require, write_json

CONTRACT = "counterexample-reduction-v1.json"
CONTRACT_DIGEST = "318b3958cbf2842df34e1ab746ea662acce25a42a2d6a5d1a088a2019bba47ab"


def direct_projection(item):
    return isinstance(item, dict) and "sql" not in item and bool(item.get("columns"))


def record(path, observation, signal, proposals, probe):
    reduction_path = path.with_suffix(".reduction.json")
    write_json(path, {**observation, "shrunk": None, "reductionEvidence": reduction_path.name})
    result = {
        "originalDigest": digest(path), "contract": CONTRACT_DIGEST, "signal": signal,
        "status": "not-applicable", "attempts": [], "shrunk": None,
    }
    original = observation["original"]
    try:
        contract = TOOL / CONTRACT
        require(digest(contract) == CONTRACT_DIGEST, "reduction-contract-mismatch")
        specification = json.loads(contract.read_text())
        if signal in specification["admittedSignals"] and direct_projection(original):
            result.update(reduce(original, signal, proposals, probe, specification["maximumReductionProbes"], result["attempts"]))
    except Exception as error:
        result.update(status="reducer-failure", failure={"type": type(error).__name__, "detail": str(error)})
    write_json(reduction_path, result)


def check(item, probe, attempts):
    result = {"input": item}
    try:
        probe(item)
    except Failure as error:
        result.update(outcome=error.signal, detail=error.detail)
    except Exception as error:
        result.update(outcome="unexpected-error", detail=str(error), type=type(error).__name__)
    else:
        result["outcome"] = "passed"
    attempts.append(result)
    return result["outcome"]


def size(item):
    return len(json.dumps(item, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode())


def reduce(original, signal, proposals, probe, maximum, attempts):
    baseline = check(original, probe, attempts)
    if baseline == "timeout":
        return {"status": "deadline-exhausted"}
    if baseline != signal:
        return {"status": "original-not-reproduced"}
    limit = size(original)
    for index, candidate in enumerate(proposals(original)):
        if index >= maximum or len(attempts) + 2 > maximum:
            return {"status": "probe-budget-exhausted"}
        if not direct_projection(candidate) or size(candidate) >= limit:
            continue
        outcome = check(candidate, probe, attempts)
        if outcome == "timeout":
            return {"status": "deadline-exhausted"}
        if outcome != signal:
            continue
        repeated = check(candidate, probe, attempts)
        if repeated == "timeout":
            return {"status": "deadline-exhausted"}
        if repeated == signal and attempts[-1]["detail"] == attempts[-2]["detail"]:
            return {"status": "reproduced", "shrunk": candidate, "confirmedFailure": attempts[-1]}
    return {"status": "no-confirmed-smaller-input"}
