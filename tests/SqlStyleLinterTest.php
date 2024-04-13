<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Samuelnogueira\SqlstyleFixer\Fixer;
use SplFileInfo;

use function basename;
use function Safe\file_get_contents;

final class SqlStyleLinterTest extends TestCase
{
    private Fixer $subject;

    #[DataProvider('provideGoodExamplesFromWebsite')]
    public function testGoodExamplesFromWebsite(string $filename): void
    {
        $sql = file_get_contents($filename);

        $result = $this->subject->fixString($sql);

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

        $result = $this->subject->fixString($sql);

        self::assertStringEqualsFile($fileAfter, $result);
    }

    public function testInlineFunctionArguments(): void
    {
        self::assertEquals(
            <<<'SQL'
SELECT LAG(my_column)
SQL,
            $this->subject->fixString(
                <<<'SQL'
SELECT LAG(
    my_column
)
SQL
            )
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

        $this->subject = new Fixer(debug: true);
    }
}
