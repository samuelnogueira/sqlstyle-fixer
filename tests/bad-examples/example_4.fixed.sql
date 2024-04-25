SELECT column_name1,
       column_name2,
       column_name3
  FROM table_1
  JOIN table_2
    ON table_1.id = table_2.id
 WHERE clouds = true
   AND gem = true
 GROUP BY 1, 2, 3
HAVING column_name1 > 0
   AND column_name2 > 0
