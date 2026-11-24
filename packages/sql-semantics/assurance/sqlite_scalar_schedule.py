"""Run scalar domain schedules and record independent finite and exploration checks."""

import time
from collections import Counter
from typing import Callable, NamedTuple

from run import Failure, Ledger, require, write_json
from run_sqlite_case import EXCEPTION, probe
from sqlite_domain import complete_observations


class ScalarSchedule(NamedTuple):
    requests: Callable
    negatives: Callable
    finite_id: Callable
    finite_ids: Callable
    observed_forms: Callable
    required_forms: Callable
    comparator_controls: Callable


def execute_schedule(args, specification, db, worker_factory, schedule, observe=probe, *, measured_input=None,
                     paired_finite=None):
    domain, settings = specification["finite"], specification["exploration"]
    negative_cases = tuple(schedule.negatives())
    expected_negatives = len(specification["negative"]) + len(specification.get("analysisNegative", ()))
    require(len(negative_cases) == expected_negatives, "sqlite-scalar-negative-schedule",
            {"contract": expected_negatives, "scheduled": len(negative_cases)})
    seen, observed = Counter(), Counter()
    awaiting_direct, checked_direct = {}, set()
    comparator = schedule.comparator_controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in schedule.requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                reply = worker.request(current)
                measurement = observe(item, reply, db, ledger, key, original=current.get("sql"))
                measured = reply if measured_input is None else measured_input(reply, measurement)
                if key.startswith("finite/"):
                    coordinate = schedule.finite_id(measured, domain)
                    seen[coordinate] += 1
                    if paired_finite is not None:
                        ordinal = key.removeprefix("finite/")
                        require(ordinal not in awaiting_direct, "sqlite-scalar-duplicate-finite-ordinal", key)
                        awaiting_direct[ordinal] = (coordinate, paired_finite(measured))
                if paired_finite is not None and key.startswith("direct/") and key[7:].isdigit():
                    ordinal = key[7:]
                    require(ordinal in awaiting_direct, "sqlite-scalar-unpaired-direct-finite", key)
                    coordinate, counterpart = awaiting_direct.pop(ordinal)
                    require(paired_finite(measured) == counterpart and coordinate not in checked_direct,
                            "sqlite-scalar-direct-finite-mismatch", key)
                    checked_direct.add(coordinate)
                if key.startswith("seed/"):
                    observed.update(schedule.observed_forms(measured))
            for index, (current, status, reason) in enumerate(negative_cases):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                if status == "type-rejected":
                    expected_status, exception = "error", "TypeError"
                else:
                    expected_status = "analysis-rejected" if status == "syntax-rejected" else status
                    exception = EXCEPTION + ({
                        "rejected": "InvalidConstruction",
                        "analysis-rejected": "SemanticException",
                        "syntax-rejected": "AnalysisException",
                    }[status])
                fragments = (reason,) if isinstance(reason, str) else reason
                require(reply["status"] == expected_status and reply["exception"] == exception
                        and all(fragment in reply["message"] for fragment in fragments),
                        "sqlite-scalar-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == schedule.finite_ids(domain) and set(seen.values()) == {1}, "sqlite-scalar-finite-incomplete", sorted(seen))
            if paired_finite is not None:
                require(not awaiting_direct and checked_direct == seen.keys(), "sqlite-scalar-direct-finite-incomplete",
                        {"pending": sorted(awaiting_direct), "direct": sorted(checked_direct)})
            missing = schedule.required_forms(domain) - observed.keys()
            require(not missing, "sqlite-scalar-unobserved-forms", sorted(missing))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "failure": str(error), "detail": detail, "shrunk": None})
            raise
        checks = {"counts": counts, "finiteIds": sorted(seen), "forms": dict(observed), "php": worker.environment,
                  "controls": comparator}
        if paired_finite is not None:
            checks["directFiniteIds"] = sorted(checked_direct)
        return checks
