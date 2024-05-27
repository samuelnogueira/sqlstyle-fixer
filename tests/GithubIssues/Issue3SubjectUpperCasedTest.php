<?php
declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests\GithubIssues;

use PHPUnit\Framework\TestCase;
use Samuelnogueira\SqlstyleFixer\SqlStyleFixer;
use Samuelnogueira\SqlstyleFixerTests\Tester\FixerTester;

final class Issue3SubjectUpperCasedTest extends TestCase
{
    private FixerTester $fixerTester;

    public function test(): void
    {
        self::assertEquals(
            <<<'PHP'
<?php
            $this->addSql(<<<'SQL'
INSERT INTO tbl_xz9qr (col_ef34, `subject`)
VALUES ('my_value_1', 'my_subject_1')
SQL
        );
PHP,
            $this->fixerTester->fixCode(
                <<<'PHP'
<?php
            $this->addSql(<<<'SQL'
            INSERT INTO tbl_xz9qr
                (col_ef34, subject)
            VALUES
                ('my_value_1', 'my_subject_1')
SQL
        );
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
