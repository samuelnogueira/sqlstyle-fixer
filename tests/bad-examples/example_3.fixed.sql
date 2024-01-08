SELECT budget_forecast.account_id,
       date_details.fiscal_year,
       date_details.fiscal_quarter,
       date_details.fiscal_quarter_name,
       cost_category.cost_category_level_1,
       cost_category.cost_category_level_2
  FROM budget_forecast_cogs_opex AS budget_forecast
       LEFT JOIN date_details
       ON date_details.first_day_of_month = budget_forecast.accounting_period
       LEFT JOIN cost_category
       ON budget_forecast.unique_account_name = cost_category.unique_account_name