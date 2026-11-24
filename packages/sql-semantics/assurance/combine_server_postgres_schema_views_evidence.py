"""Reconcile PostgreSQL namespace view composition receipts for both stock releases."""

from combine_server_join_evidence import main
import run_server_postgres_schema_views

if __name__ == "__main__":
    main(run_server_postgres_schema_views)
