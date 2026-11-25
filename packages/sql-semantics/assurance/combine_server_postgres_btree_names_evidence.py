"""Replay automatic PostgreSQL B-tree name checks against the fixed laws."""

from combine_server_join_evidence import main
import run_server_postgres_btree_names


if __name__ == "__main__":
    main(run_server_postgres_btree_names)
