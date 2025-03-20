<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests;

use Samuelnogueira\SqlstyleFixer\SqlStyleFixer;
use PHPUnit\Framework\TestCase;
use Samuelnogueira\SqlstyleFixerTests\Tester\FixerTester;

final class SqlStyleFixerTest extends TestCase
{
    private FixerTester $fixerTester;

    public function testFixHeredocSQL(): void
    {
        self::assertEquals(
            <<<'PHP'
<?php
$a = <<<SQL
SELECT FirstName, LastName
  FROM Employees
 WHERE Department = 'Sales';
SQL;
PHP,
            $this->fixerTester->fixCode(
                <<<'PHP'
<?php
$a = <<<SQL
SELECT FirstName, LastName
FROM Employees
WHERE Department = 'Sales';
SQL;
PHP,
            ),
        );
    }

    public function testFixNowdocSQL(): void
    {
        self::assertEquals(
            <<<'PHP'
<?php
$a = <<<'SQL'
SELECT o.order_id, o.order_date, c.customer_name
  FROM orders AS o
       INNER JOIN customers AS c
       ON o.customer_id = c.customer_id
 WHERE c.city = :city
 ORDER BY o.order_date DESC
SQL;
PHP,
            $this->fixerTester->fixCode(
                <<<'PHP'
<?php
$a = <<<'SQL'
SELECT o.order_id, o.order_date, c.customer_name
    FROM orders AS o
    INNER JOIN customers AS c ON o.customer_id = c.customer_id
    WHERE c.city = :city
    ORDER BY o.order_date DESC
SQL;
PHP,
            ),
        );
    }

    public function testFixHeredocDQL(): void
    {
        self::assertEquals(
            <<<'PHP'
<?php
$a = <<<DQL
SELECT u
  FROM MyProject\Model\User u
 WHERE u.age > 20
DQL;
PHP,
            $this->fixerTester->fixCode(
                <<<'PHP'
<?php
$a = <<<DQL
SELECT u FROM MyProject\Model\User u WHERE u.age > 20
DQL;
PHP,
            ),
        );
    }

    public function testFixNowdocDQL(): void
    {
        self::assertEquals(
            <<<'PHP'
<?php
$a = <<<'DQL'
SELECT p
  FROM MyProject\Model\Post p
  JOIN p.author a
 WHERE p.published = true
   AND a.followersCount > 100
DQL;
PHP,
            $this->fixerTester->fixCode(
                <<<'PHP'
<?php
$a = <<<'DQL'
SELECT p
FROM MyProject\Model\Post p
JOIN p.author a
WHERE p.published = true
AND a.followersCount > 100
DQL;
PHP,
            ),
        );
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->fixerTester = new FixerTester(new SqlStyleFixer());
    }
}
