"""Replay the PostgreSQL native text B-tree comparison from both pinned releases."""

from combine_server_join_evidence import main
import run_server_postgres_btree_text

if __name__ == "__main__":
    main(run_server_postgres_btree_text)
