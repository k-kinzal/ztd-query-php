"""Independently replay the derived projection alias receipts."""

from combine_server_join_evidence import main
import run_server_mysql_derived_projection_alias


if __name__ == "__main__":
    main(run_server_mysql_derived_projection_alias)
