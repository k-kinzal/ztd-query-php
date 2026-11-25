"""Replay the native declared PostgreSQL B-tree collations across both profiles."""

from combine_server_join_evidence import main
import run_server_postgres_btree_declared

if __name__ == "__main__":
    main(run_server_postgres_btree_declared)
