"""Reconcile PostgreSQL view replacement receipts for both stock releases."""

from combine_server_join_evidence import main
import run_server_postgres_schema_view_replacement

if __name__ == "__main__":
    main(run_server_postgres_schema_view_replacement)
