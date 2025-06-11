<?php

declare(strict_types=1);

namespace Bermuda\Tokenizer;

/**
 * Optimized PHP tokenizer for finding class definitions
 *
 * This tokenizer efficiently parses PHP source code to find declarations of:
 * - Classes (regular, abstract, final, readonly)
 * - Interfaces
 * - Traits
 * - Enums
 *
 * Anonymous classes are automatically ignored.
 */
class Tokenizer implements TokenizerInterface
{
    /**
     * Parse PHP source code and return found class declarations
     *
     * @param string $source PHP source code to parse
     * @param int $mode Bitwise combination of SEARCH_* constants
     * @return array<ClassInfo> Array of found declarations
     */
    public function parse(string $source, int $mode = self::SEARCH_ALL): array
    {
        $tokens = token_get_all($source);
        $declarations = [];
        $tokenCount = count($tokens);

        $currentNamespace = '';
        $isAbstract = false;
        $isFinal = false;
        $isReadonly = false;

        // State tracking for PHP attributes
        $inAttribute = false;
        $attributeBrackets = 0;

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            // Handle single-character tokens
            if (!is_array($token)) {
                if ($token === '[' && $inAttribute) {
                    $attributeBrackets++;
                } elseif ($token === ']' && $inAttribute) {
                    $attributeBrackets--;
                    if ($attributeBrackets === 0) {
                        $inAttribute = false;
                    }
                }
                continue;
            }

            if (count($token) < 2) {
                continue;
            }

            $tokenId = $token[0];

            // Skip whitespace and comments
            if (in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            // Handle PHP attributes
            if ($tokenId === T_ATTRIBUTE) {
                $inAttribute = true;
                $attributeBrackets = 1;
                continue;
            }

            // Skip tokens inside attributes
            if ($inAttribute) {
                continue;
            }

            // Handle namespace declarations
            if ($tokenId === T_NAMESPACE) {
                $namespaceResult = $this->parseNamespace($tokens, $i);
                $currentNamespace = $namespaceResult['namespace'];
                $i = $namespaceResult['nextIndex'] - 1; // -1 because for loop will increment
                continue;
            }

            // Skip use statements
            if ($tokenId === T_USE) {
                $i = $this->skipUseStatement($tokens, $i);
                continue;
            }

            // Handle class modifiers
            if ($tokenId === T_ABSTRACT) {
                $isAbstract = true;
                continue;
            }
            if ($tokenId === T_FINAL) {
                $isFinal = true;
                continue;
            }
            if ($tokenId === T_READONLY) {
                $isReadonly = true;
                continue;
            }

            // Handle class declarations
            if ($tokenId === T_CLASS && ($mode & self::SEARCH_CLASSES) !== 0) {
                $result = $this->parseDeclaration($tokens, $i, $currentNamespace, 'class', $isAbstract, $isFinal, $isReadonly);
                if ($result) {
                    $declarations[] = $result['declaration'];
                    $i = $result['nextIndex'] - 1; // -1 because for loop will increment
                }
                $isAbstract = $isFinal = $isReadonly = false;
                continue;
            }

            // Handle interface declarations
            if ($tokenId === T_INTERFACE && ($mode & self::SEARCH_INTERFACES) !== 0) {
                $result = $this->parseDeclaration($tokens, $i, $currentNamespace, 'interface');
                if ($result) {
                    $declarations[] = $result['declaration'];
                    $i = $result['nextIndex'] - 1;
                }
                continue;
            }

            // Handle trait declarations
            if ($tokenId === T_TRAIT && ($mode & self::SEARCH_TRAITS) !== 0) {
                $result = $this->parseDeclaration($tokens, $i, $currentNamespace, 'trait');
                if ($result) {
                    $declarations[] = $result['declaration'];
                    $i = $result['nextIndex'] - 1;
                }
                continue;
            }

            // Handle enum declarations
            if ($tokenId === T_ENUM && ($mode & self::SEARCH_ENUMS) !== 0) {
                $result = $this->parseDeclaration($tokens, $i, $currentNamespace, 'enum');
                if ($result) {
                    $declarations[] = $result['declaration'];
                    $i = $result['nextIndex'] - 1;
                }
                continue;
            }

            // Reset modifiers on unexpected tokens
            if (!in_array($tokenId, [T_ABSTRACT, T_FINAL, T_READONLY, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $isAbstract = $isFinal = $isReadonly = false;
            }
        }

        return $declarations;
    }

    /**
     * Parse namespace declaration and extract namespace name
     *
     * @param array $tokens Array of tokens
     * @param int $index Current token index
     * @return array{namespace: string, nextIndex: int}
     */
    private function parseNamespace(array $tokens, int $index): array
    {
        $index++; // Skip T_NAMESPACE
        $namespace = '';

        // Skip any whitespace after namespace keyword
        while ($index < count($tokens)) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                $index++;
                continue;
            }
            break;
        }

        // Check for global namespace: namespace { ... }
        if ($index < count($tokens) && $tokens[$index] === '{') {
            return ['namespace' => '', 'nextIndex' => $index];
        }

        // Collect namespace name
        while ($index < count($tokens)) {
            $token = $tokens[$index];

            // Stop at terminators
            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }
                $index++;
                continue;
            }

            // Collect T_STRING, T_NS_SEPARATOR and T_NAME_QUALIFIED tokens
            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR || $token[0] === T_NAME_QUALIFIED) {
                $namespace .= $token[1];
                $index++;
                continue;
            }

            // Skip whitespace
            if ($token[0] === T_WHITESPACE) {
                $index++;
                continue;
            }

            // Any other token type stops parsing
            break;
        }

        // Clean up namespace
        $namespace = trim($namespace);

        return [
            'namespace' => $namespace,
            'nextIndex' => $index
        ];
    }

    /**
     * Skip use statement tokens until semicolon
     *
     * @param array $tokens Array of tokens
     * @param int $index Current token index
     * @return int Next index after use statement
     */
    private function skipUseStatement(array $tokens, int $index): int
    {
        while ($index < count($tokens)) {
            $token = $tokens[$index];
            if ($token === ';') {
                return $index + 1;
            }
            $index++;
        }
        return $index;
    }

    /**
     * Parse any declaration (class, interface, trait, enum)
     * Returns null for anonymous classes
     *
     * @param array $tokens Array of tokens
     * @param int $index Current token index
     * @param string $namespace Current namespace
     * @param string $type Declaration type ('class', 'interface', 'trait', 'enum')
     * @param bool $isAbstract Whether class is abstract (only for classes)
     * @param bool $isFinal Whether class is final (only for classes)
     * @param bool $isReadonly Whether class is readonly (only for classes)
     * @return array{declaration: ClassInfo, nextIndex: int}|null
     */
    private function parseDeclaration(
        array $tokens,
        int $index,
        string $namespace,
        string $type,
        bool $isAbstract = false,
        bool $isFinal = false,
        bool $isReadonly = false
    ): ?array {
        $index++; // Skip declaration token (T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM)

        // Find declaration name - skip anonymous classes for class type
        $nameToken = $this->findNextMeaningfulToken($tokens, $index);
        if (!$nameToken || $nameToken['id'] !== T_STRING) {
            return null;
        }

        $declarationName = $nameToken['value'];
        $index = $nameToken['index'] + 1;

        $fullName = $namespace ? $namespace . '\\' . $declarationName : $declarationName;

        $declaration = new ClassInfo(
            $fullName,
            $type,
            $isAbstract,
            $isFinal,
            $isReadonly
        );

        return [
            'declaration' => $declaration,
            'nextIndex' => $index
        ];
    }

    /**
     * Find next meaningful token (skip whitespace and comments)
     *
     * @param array $tokens Array of tokens
     * @param int $startIndex Starting index
     * @return array{id: int|null, value: string, index: int}|null
     */
    private function findNextMeaningfulToken(array $tokens, int $startIndex): ?array
    {
        for ($i = $startIndex; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                return [
                    'id' => null,
                    'value' => $token,
                    'index' => $i
                ];
            }

            if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                return [
                    'id' => $token[0],
                    'value' => $token[1],
                    'index' => $i
                ];
            }
        }

        return null;
    }
}