"""Independently replay PostgreSQL B-tree catalog, write and runtime evidence."""

from combine_server_join_evidence import main
import run_server_postgres_create_btree


if __name__ == "__main__":
    main(run_server_postgres_create_btree)
