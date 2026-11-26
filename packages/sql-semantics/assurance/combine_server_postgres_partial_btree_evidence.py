"""Replay the native partial B-tree comparisons and applied predicate faults."""

from combine_server_join_evidence import main
import run_server_postgres_partial_btree


if __name__ == "__main__":
    main(run_server_postgres_partial_btree)
