"""Reconcile native PostgreSQL physical table-width receipts."""

from combine_server_join_evidence import main
import run_server_postgres_table_width

if __name__ == "__main__":
    main(run_server_postgres_table_width)
