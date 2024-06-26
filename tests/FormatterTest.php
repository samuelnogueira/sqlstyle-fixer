<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Samuelnogueira\SqlstyleFixer\Formatter;
use SplFileInfo;

use function basename;
use function Safe\file_get_contents;

final class FormatterTest extends TestCase
{
    private Formatter $subject;

    #[DataProvider('provideGoodExamplesFromWebsite')]
    public function testGoodExamplesFromWebsite(string $filename): void
    {
        $sql = file_get_contents($filename);

        $result = $this->subject->formatString($sql);

        self::assertEquals($sql, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideGoodExamplesFromWebsite(): iterable
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            directory: __DIR__ . '/good-examples-from-sqlstyle-guide-website',
            flags: FilesystemIterator::SKIP_DOTS,
        );

        foreach (new RecursiveIteratorIterator($directoryIterator) as $fileInfo) {
            assert($fileInfo instanceof SplFileInfo);

            yield $fileInfo->getBasename() => [$fileInfo->getPathname()];
        }
    }

    #[DataProvider('provideBadExamples')]
    public function testBadExamples(string $fileBefore, string $fileAfter): void
    {
        $sql = file_get_contents($fileBefore);

        $result = $this->subject->formatString($sql);

        self::assertStringEqualsFile($fileAfter, $result);
    }

    public function testInlineFunctionArguments(): void
    {
        self::assertEquals(
            <<<'SQL'
SELECT LAG(my_column)
SQL,
            $this->subject->formatString(
                <<<'SQL'
SELECT LAG(
    my_column
)
SQL,
            ),
        );
    }

    public function testCaseStatement(): void
    {
        self::assertEquals(
            <<<'SQL'
SELECT CASE
       WHEN COUNT(*) = 1 THEN 'One-time Customer'
       WHEN COUNT(*) = 2 THEN 'Repeated Customer'
       WHEN COUNT(*) = 3 THEN 'Frequent Customer'
       ELSE 'Loyal Customer'
       END AS customerType
  FROM orders
 GROUP BY customerName
SQL,
            $this->subject->formatString(
                <<<'SQL'
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'One-time Customer'
        WHEN COUNT(*) = 2 THEN 'Repeated Customer'
        WHEN COUNT(*) = 3 THEN 'Frequent Customer'
        ELSE 'Loyal Customer'
    END AS customerType
FROM orders
GROUP BY customerName
SQL,
            ),
        );
    }

    public function testFunctions(): void
    {
        self::assertEquals(
            <<<'SQL'
SELECT AVG(b.height) AS average_height,
       AVG(b.diameter) AS average_diameter,
       COUNT(*) AS total
  FROM botanic_garden_flora AS b
SQL,
            $this->subject->formatString(
                <<<'SQL'
SELECT
AVG(b.height) AS average_height,
  AVG(b.diameter) AS average_diameter,
   COUNT(*) AS total
  FROM botanic_garden_flora AS b
SQL,
            ),
        );
    }

    public function testOrderBy(): void
    {
        self::assertEquals(
            <<<'SQL'
SELECT 1, 2
 ORDER BY 1, 2 DESC
SQL,
            $this->subject->formatString(
                <<<'SQL'
SELECT 1,2 ORDER BY 1,2 DESC
SQL,
            ),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function provideBadExamples(): iterable
    {
        $allFiles = [
            [__DIR__ . '/bad-examples/example_1.sql', __DIR__ . '/bad-examples/example_1.fixed.sql'],
            [__DIR__ . '/bad-examples/example_2.sql', __DIR__ . '/bad-examples/example_2.fixed.sql'],
            [__DIR__ . '/bad-examples/example_3.sql', __DIR__ . '/bad-examples/example_3.fixed.sql'],
            [__DIR__ . '/bad-examples/example_4.sql', __DIR__ . '/bad-examples/example_4.fixed.sql'],
            [__DIR__ . '/bad-examples/tough_1.sql', __DIR__ . '/bad-examples/tough_1.fixed.sql'],
        ];

        foreach ($allFiles as $files) {
            yield basename($files[0]) => $files;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Formatter();
    }
}
