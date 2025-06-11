<?php

declare(strict_types=1);

namespace Bermuda\Tokenizer;

/**
 * Interface for PHP tokenizer with search mode constants
 *
 * Defines the contract for tokenizers that can parse PHP source code
 * and find class declarations with configurable search modes using bitwise flags.
 *
 */
interface TokenizerInterface
{
    /**
     * Search mode constants for bitwise operations
     */
    public const int SEARCH_CLASSES = 1;
    public const int SEARCH_INTERFACES = 2;
    public const int SEARCH_TRAITS = 4;
    public const int SEARCH_ENUMS = 8;
    public const int SEARCH_ALL = 15; // 1 + 2 + 4 + 8

    /**
     * Parse PHP source code and return found class declarations
     *
     * This method tokenizes PHP source code and extracts information about
     * class declarations including classes, interfaces, traits, and enums.
     * Anonymous classes are automatically ignored.
     *
     * @param string $source PHP source code to parse
     * @param int $mode Bitwise combination of SEARCH_* constants (defaults to SEARCH_ALL)
     * @return array<ClassInfo> Array of found declarations
     * @throws \InvalidArgumentException If source code is invalid
     */
    public function parse(string $source, int $mode = self::SEARCH_ALL): array;
}
