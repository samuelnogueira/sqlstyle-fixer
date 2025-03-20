SELECT
  DATE(submission_timestamp) AS day
FROM
  telemetry.main
LIMIT
  10
