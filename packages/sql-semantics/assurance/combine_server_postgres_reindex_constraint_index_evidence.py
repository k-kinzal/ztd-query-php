"""Replay PostgreSQL constraint-index rebuild reports for the pinned releases."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_constraint_index

if __name__ == "__main__":
    main(run_server_postgres_reindex_constraint_index)
