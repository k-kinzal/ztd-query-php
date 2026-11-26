"""Independently replay the CTE alias projection receipts."""

from combine_server_join_evidence import main
import run_server_mysql_with_projection_alias


if __name__ == "__main__":
    main(run_server_mysql_with_projection_alias)
