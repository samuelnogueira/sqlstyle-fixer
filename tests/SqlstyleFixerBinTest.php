<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpSubprocess;

final class SqlstyleFixerBinTest extends TestCase
{
    public function testStdinStdout(): void
    {
        $process = new PhpSubprocess([__DIR__ . '/../bin/sqlstyle-fixer']);
        $process
            ->setInput(
                <<<'SQL'
SELECT file_hash  -- stored ssdeep hash
FROM file_system WHERE file_name = '.vimrc';
SQL,
            )
            ->mustRun();

        self::assertEquals(
            <<<'SQL'
SELECT file_hash  -- stored ssdeep hash
  FROM file_system
 WHERE file_name = '.vimrc';
SQL,
            $process->getOutput(),
        );
    }
}
