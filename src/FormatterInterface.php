<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

interface FormatterInterface
{
    public function formatString(string $sql): string;
}
