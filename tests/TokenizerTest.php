<?php

declare(strict_types=1);

namespace Bermuda\Tokenizer\Tests;

use Bermuda\Tokenizer\ClassInfo;
use Bermuda\Tokenizer\Tokenizer;
use Bermuda\Tokenizer\TokenizerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Tokenizer::class)]
#[CoversClass(ClassInfo::class)]
class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

    // ========================================
    // BASIC FUNCTIONALITY TESTS
    // ========================================

    #[Test]
    #[TestDox('Tokenizer implements TokenizerInterface correctly')]
    public function tokenizerImplementsInterface(): void
    {
        // Test that constants are accessible
        $this->assertSame(1, TokenizerInterface::SEARCH_CLASSES);
        $this->assertSame(2, TokenizerInterface::SEARCH_INTERFACES);
        $this->assertSame(4, TokenizerInterface::SEARCH_TRAITS);
        $this->assertSame(8, TokenizerInterface::SEARCH_ENUMS);
        $this->assertSame(15, TokenizerInterface::SEARCH_ALL);
    }

    #[Test]
    #[TestDox('Bitwise operations work correctly with constants')]
    public function bitwiseOperationsWorkCorrectly(): void
    {
        // Test includes operation
        $this->assertTrue((TokenizerInterface::SEARCH_ALL & TokenizerInterface::SEARCH_CLASSES) !== 0);
        $this->assertTrue((TokenizerInterface::SEARCH_CLASSES & TokenizerInterface::SEARCH_CLASSES) !== 0);
        $this->assertFalse((TokenizerInterface::SEARCH_CLASSES & TokenizerInterface::SEARCH_INTERFACES) !== 0);

        // Test combine operation
        $combined = TokenizerInterface::SEARCH_CLASSES | TokenizerInterface::SEARCH_INTERFACES;
        $this->assertSame(3, $combined); // 1 | 2 = 3
        $this->assertTrue(($combined & TokenizerInterface::SEARCH_CLASSES) !== 0);
        $this->assertTrue(($combined & TokenizerInterface::SEARCH_INTERFACES) !== 0);
        $this->assertFalse(($combined & TokenizerInterface::SEARCH_TRAITS) !== 0);

        // Test except operation
        $excepted = TokenizerInterface::SEARCH_ALL & ~TokenizerInterface::SEARCH_ENUMS;
        $this->assertSame(7, $excepted); // 15 & ~8 = 7
        $this->assertTrue(($excepted & TokenizerInterface::SEARCH_CLASSES) !== 0);
        $this->assertTrue(($excepted & TokenizerInterface::SEARCH_INTERFACES) !== 0);
        $this->assertTrue(($excepted & TokenizerInterface::SEARCH_TRAITS) !== 0);
        $this->assertFalse(($excepted & TokenizerInterface::SEARCH_ENUMS) !== 0);
    }

    #[Test]
    #[TestDox('Parses simple class declaration correctly')]
    public function parsesSimpleClass(): void
    {
        $code = '<?php class SimpleClass {}';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ClassInfo::class, $result[0]);
        $this->assertSame('SimpleClass', $result[0]->name);
        $this->assertSame('class', $result[0]->type);
        $this->assertTrue($result[0]->isClass);
        $this->assertTrue($result[0]->isConcrete);
        $this->assertFalse($result[0]->isAbstract);
    }

    #[Test]
    #[TestDox('Parses namespaced class with correct namespace extraction')]
    public function parsesNamespacedClass(): void
    {
        $code = '<?php namespace App\\Models; class User {}';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('App\\Models\\User', $result[0]->fullQualifiedName);
        $this->assertSame('User', $result[0]->name);
        $this->assertSame('App\\Models', $result[0]->namespace);
    }

    #[Test]
    #[TestDox('Ignores anonymous classes completely')]
    public function ignoresAnonymousClasses(): void
    {
        $code = '<?php 
        class RegularClass {}
        $obj = new class { 
            public function test() {} 
        };
        $another = new class extends SomeParent implements SomeInterface {
            // complex anonymous class
        };
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('RegularClass', $result[0]->name);
    }

    #[Test]
    #[TestDox('Parses all declaration types with correct modifiers')]
    public function parsesAllDeclarationTypes(): void
    {
        $code = '<?php
        namespace App;
        
        abstract class AbstractClass {}
        final class FinalClass {}
        readonly class ReadonlyClass {}
        interface MyInterface {}
        trait MyTrait {}
        enum MyEnum {}
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(6, $result);

        // Group by name for easier testing
        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        // Test abstract class
        $this->assertTrue($byName['AbstractClass']->isAbstract);
        $this->assertTrue($byName['AbstractClass']->isClass);
        $this->assertFalse($byName['AbstractClass']->isConcrete);

        // Test final class
        $this->assertTrue($byName['FinalClass']->isFinal);
        $this->assertTrue($byName['FinalClass']->isConcrete);

        // Test readonly class
        $this->assertTrue($byName['ReadonlyClass']->isReadonly);
        $this->assertTrue($byName['ReadonlyClass']->isConcrete);

        // Test interface
        $this->assertTrue($byName['MyInterface']->isInterface);
        $this->assertFalse($byName['MyInterface']->isClass);
        $this->assertFalse($byName['MyInterface']->isConcrete);

        // Test trait
        $this->assertTrue($byName['MyTrait']->isTrait);
        $this->assertFalse($byName['MyTrait']->isConcrete);

        // Test enum
        $this->assertTrue($byName['MyEnum']->isEnum);
        $this->assertFalse($byName['MyEnum']->isConcrete);
    }

    #[Test]
    #[TestDox('Filters declarations by search mode correctly')]
    public function filtersDeclarationsBySearchMode(): void
    {
        $code = '<?php
        namespace App;
        
        class TestClass {}
        interface TestInterface {}
        trait TestTrait {}
        enum TestEnum {}
        ';

        // Test individual modes
        $classesOnly = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_CLASSES);
        $this->assertCount(1, $classesOnly);
        $this->assertTrue($classesOnly[0]->isClass);

        $interfacesOnly = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_INTERFACES);
        $this->assertCount(1, $interfacesOnly);
        $this->assertTrue($interfacesOnly[0]->isInterface);

        $traitsOnly = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_TRAITS);
        $this->assertCount(1, $traitsOnly);
        $this->assertTrue($traitsOnly[0]->isTrait);

        $enumsOnly = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_ENUMS);
        $this->assertCount(1, $enumsOnly);
        $this->assertTrue($enumsOnly[0]->isEnum);

        // Test ALL mode
        $all = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_ALL);
        $this->assertCount(4, $all);
    }

    #[Test]
    #[TestDox('Handles complex inheritance and attributes correctly')]
    public function handlesComplexInheritanceAndAttributes(): void
    {
        $code = '<?php
        namespace App\\Domain;
        
        #[Entity]
        #[Table("users")]
        class User extends BaseUser implements UserInterface, SerializableInterface {
            // some content
        }
        
        $anonymousChild = new class extends User {};
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('User', $result[0]->name);
        $this->assertSame('App\\Domain', $result[0]->namespace);
        $this->assertSame('App\\Domain\\User', $result[0]->fullQualifiedName);
        $this->assertTrue($result[0]->isConcrete);
    }

    #[Test]
    #[TestDox('Handles nested anonymous classes correctly')]
    public function handlesNestedAnonymousClasses(): void
    {
        $code = '<?php
        class OuterClass {
            public function createAnonymous() {
                return new class {
                    public function inner() {
                        return new class {
                            public function deepNested() {
                                return new class {};
                            }
                        };
                    }
                };
            }
        }
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('OuterClass', $result[0]->name);
        $this->assertSame('', $result[0]->namespace);
    }

    #[Test]
    #[TestDox('Ignores fake classes in strings and comments')]
    public function ignoresFakeClassesInStringsAndComments(): void
    {
        $code = '<?php
        // This is not a class: "class FakeClass {}"
        /* 
         * Another fake: class CommentClass {}
         * Multi-line comment with class definitions
         */
        
        class RealClass {
            public function test() {
                $code = "class StringClass {}";
                $heredoc = <<<EOT
                class HeredocClass {}
                EOT;
                return $code;
            }
        }
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('RealClass', $result[0]->name);
        $this->assertTrue($result[0]->isConcrete);
    }

    #[Test]
    #[TestDox('Virtual properties work correctly for all declaration types')]
    public function virtualPropertiesWorkCorrectly(): void
    {
        $code = '<?php
        namespace Test;
        
        class ConcreteClass {}
        abstract class AbstractClass {}
        final class FinalClass {}
        interface TestInterface {}
        trait TestTrait {}
        enum TestEnum {}
        ';

        $result = $this->tokenizer->parse($code);
        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        // Test concrete class
        $concrete = $byName['ConcreteClass'];
        $this->assertTrue($concrete->isClass);
        $this->assertTrue($concrete->isConcrete);
        $this->assertFalse($concrete->isInterface);
        $this->assertFalse($concrete->isTrait);
        $this->assertFalse($concrete->isEnum);
        $this->assertFalse($concrete->isAbstract);

        // Test abstract class
        $abstract = $byName['AbstractClass'];
        $this->assertTrue($abstract->isClass);
        $this->assertFalse($abstract->isConcrete);
        $this->assertTrue($abstract->isAbstract);

        // Test interface
        $interface = $byName['TestInterface'];
        $this->assertTrue($interface->isInterface);
        $this->assertFalse($interface->isClass);
        $this->assertFalse($interface->isConcrete);

        // Test trait
        $trait = $byName['TestTrait'];
        $this->assertTrue($trait->isTrait);
        $this->assertFalse($trait->isClass);
        $this->assertFalse($trait->isConcrete);

        // Test enum
        $enum = $byName['TestEnum'];
        $this->assertTrue($enum->isEnum);
        $this->assertFalse($enum->isClass);
        $this->assertFalse($enum->isConcrete);
    }

    #[Test]
    #[TestDox('Combine operation works correctly with bitwise OR')]
    public function combineOperationWorks(): void
    {
        $code = '<?php
        class TestClass {}
        interface TestInterface {}
        trait TestTrait {}
        enum TestEnum {}
        ';

        // Test combining classes and interfaces
        $classesAndInterfaces = TokenizerInterface::SEARCH_CLASSES | TokenizerInterface::SEARCH_INTERFACES;
        $result = $this->tokenizer->parse($code, $classesAndInterfaces);
        $this->assertCount(2, $result);

        $types = array_map(fn($d) => $d->type, $result);
        $this->assertContains('class', $types);
        $this->assertContains('interface', $types);
        $this->assertNotContains('trait', $types);
        $this->assertNotContains('enum', $types);

        // Test combining all individual modes should equal ALL
        $allCombined = TokenizerInterface::SEARCH_CLASSES |
            TokenizerInterface::SEARCH_INTERFACES |
            TokenizerInterface::SEARCH_TRAITS |
            TokenizerInterface::SEARCH_ENUMS;
        $this->assertSame(TokenizerInterface::SEARCH_ALL, $allCombined);

        $resultAll = $this->tokenizer->parse($code, $allCombined);
        $this->assertCount(4, $resultAll);
    }

    #[Test]
    #[TestDox('Except operation works correctly with bitwise AND NOT')]
    public function exceptOperationWorks(): void
    {
        $code = '<?php
        class TestClass {}
        interface TestInterface {}
        trait TestTrait {}
        enum TestEnum {}
        ';

        // Test excluding classes (should get interfaces, traits, enums)
        $exceptClasses = TokenizerInterface::SEARCH_ALL & ~TokenizerInterface::SEARCH_CLASSES;
        $result = $this->tokenizer->parse($code, $exceptClasses);
        $this->assertCount(3, $result);

        $types = array_map(fn($d) => $d->type, $result);
        $this->assertNotContains('class', $types);
        $this->assertContains('interface', $types);
        $this->assertContains('trait', $types);
        $this->assertContains('enum', $types);

        // Test excluding multiple types
        $exceptClassesAndInterfaces = TokenizerInterface::SEARCH_ALL &
            ~(TokenizerInterface::SEARCH_CLASSES | TokenizerInterface::SEARCH_INTERFACES);
        $result2 = $this->tokenizer->parse($code, $exceptClassesAndInterfaces);
        $this->assertCount(2, $result2);

        $types2 = array_map(fn($d) => $d->type, $result2);
        $this->assertNotContains('class', $types2);
        $this->assertNotContains('interface', $types2);
        $this->assertContains('trait', $types2);
        $this->assertContains('enum', $types2);
    }

    #[Test]
    #[TestDox('ClassInfo final class properties work correctly')]
    public function classInfoFinalClassProperties(): void
    {
        $classInfo = new ClassInfo('Test\\MyClass', 'class', true, false, false);

        $this->assertSame('Test\\MyClass', $classInfo->fullQualifiedName);
        $this->assertSame('class', $classInfo->type);
        $this->assertTrue($classInfo->isAbstract);
        $this->assertFalse($classInfo->isFinal);
        $this->assertFalse($classInfo->isReadonly);

        // Virtual properties work
        $this->assertSame('MyClass', $classInfo->name);
        $this->assertSame('Test', $classInfo->namespace);
        $this->assertTrue($classInfo->isClass);
        $this->assertFalse($classInfo->isConcrete);
    }

    #[Test]
    #[TestDox('ClassInfo exists method works correctly')]
    public function classInfoExistsMethod(): void
    {
        // Test with non-existent class
        $nonExistentClass = new ClassInfo('NonExistent\\FakeClass');
        $this->assertFalse($nonExistentClass->exists());

        // Test with built-in classes
        $stdClassInfo = new ClassInfo('stdClass');
        $this->assertTrue($stdClassInfo->exists());

        $arrayObjectInfo = new ClassInfo('ArrayObject');
        $this->assertTrue($arrayObjectInfo->exists());

        // Test with interface
        $iteratorInfo = new ClassInfo('Iterator', 'interface');
        $this->assertTrue($iteratorInfo->exists());

        // Test with built-in enum (PHP 8.1+)
        $backingTypeInfo = new ClassInfo('UnitEnum', 'interface');
        $this->assertTrue($backingTypeInfo->exists());
    }

    #[Test]
    #[TestDox('ClassInfo reflector property works for existing classes')]
    public function classInfoReflectorProperty(): void
    {
        // Test with existing class
        $stdClassInfo = new ClassInfo('stdClass');
        $this->assertTrue($stdClassInfo->exists());

        $reflector = $stdClassInfo->reflector;
        $this->assertInstanceOf(ReflectionClass::class, $reflector);
        $this->assertSame('stdClass', $reflector->getName());

        // Test that reflector is cached (same instance)
        $reflector2 = $stdClassInfo->reflector;
        $this->assertSame($reflector, $reflector2);
    }

    #[Test]
    #[TestDox('ClassInfo reflector throws exception for non-existent classes')]
    public function classInfoReflectorThrowsForNonExistent(): void
    {
        $nonExistentClass = new ClassInfo('NonExistent\\FakeClass');

        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessage('Class NonExistent\\FakeClass does not exist');

        // Accessing reflector should throw exception
        $nonExistentClass->reflector;
    }

    #[Test]
    #[TestDox('Handles empty source code gracefully')]
    public function handlesEmptySourceCode(): void
    {
        $result = $this->tokenizer->parse('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result2 = $this->tokenizer->parse('<?php');
        $this->assertIsArray($result2);
        $this->assertEmpty($result2);

        $result3 = $this->tokenizer->parse('<?php // just comments');
        $this->assertIsArray($result3);
        $this->assertEmpty($result3);
    }

    #[Test]
    #[TestDox('Handles malformed PHP gracefully')]
    public function handlesMalformedPhp(): void
    {
        // Missing class name
        $result1 = $this->tokenizer->parse('<?php class {}');
        $this->assertEmpty($result1);

        // Missing interface name
        $result2 = $this->tokenizer->parse('<?php interface {}');
        $this->assertEmpty($result2);

        // Only modifiers without class
        $result3 = $this->tokenizer->parse('<?php abstract final');
        $this->assertEmpty($result3);
    }

    #[Test]
    #[TestDox('Complex real-world PHP code parsing')]
    public function complexRealWorldCode(): void
    {
        $code = '<?php

        declare(strict_types=1);

        namespace App\\Infrastructure\\Database;

        use Doctrine\\DBAL\\Connection;
        use Psr\\Log\\LoggerInterface;

        #[Repository]
        #[Injectable]
        abstract class AbstractRepository implements RepositoryInterface
        {
            protected Connection $connection;
            
            public function __construct(Connection $connection) {
                $this->connection = $connection;
            }
        }

        final readonly class UserRepository extends AbstractRepository
        {
            public function __construct(
                Connection $connection,
                private LoggerInterface $logger
            ) {
                parent::__construct($connection);
            }
            
            public function findByEmail(string $email): ?User {
                return $this->connection->createQueryBuilder()
                    ->select("*")
                    ->from("users")
                    ->where("email = :email")
                    ->setParameter("email", $email)
                    ->executeQuery()
                    ->fetchAssociative();
            }
        }

        interface CacheableRepositoryInterface extends RepositoryInterface
        {
            public function setCacheDriver(CacheDriver $driver): void;
        }

        trait TimestampableTrait
        {
            protected \\DateTimeImmutable $createdAt;
            protected ?\\DateTimeImmutable $updatedAt = null;
        }

        enum UserStatus: string
        {
            case ACTIVE = "active";
            case INACTIVE = "inactive";
            case BANNED = "banned";
        }
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(5, $result);

        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        // Check AbstractRepository
        $abstractRepo = $byName['AbstractRepository'];
        $this->assertSame('App\\Infrastructure\\Database', $abstractRepo->namespace);
        $this->assertTrue($abstractRepo->isAbstract);
        $this->assertFalse($abstractRepo->isConcrete);

        // Check UserRepository
        $userRepo = $byName['UserRepository'];
        $this->assertTrue($userRepo->isFinal);
        $this->assertTrue($userRepo->isReadonly);
        $this->assertTrue($userRepo->isConcrete);

        // Check interface
        $cacheableInterface = $byName['CacheableRepositoryInterface'];
        $this->assertTrue($cacheableInterface->isInterface);

        // Check trait
        $timestampTrait = $byName['TimestampableTrait'];
        $this->assertTrue($timestampTrait->isTrait);

        // Check enum
        $userStatus = $byName['UserStatus'];
        $this->assertTrue($userStatus->isEnum);
    }

    #[Test]
    #[TestDox('Parses multiple namespaces correctly')]
    public function parsesMultipleNamespaces(): void
    {
        $code = '<?php
        
        namespace First\\Space {
            class FirstClass {}
            interface FirstInterface {}
        }
        
        namespace Second\\Space {
            class SecondClass {}
            trait SecondTrait {}
        }
        
        namespace {
            class GlobalClass {}
        }
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(5, $result);

        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        $this->assertSame('First\\Space', $byName['FirstClass']->namespace);
        $this->assertSame('First\\Space', $byName['FirstInterface']->namespace);
        $this->assertSame('Second\\Space', $byName['SecondClass']->namespace);
        $this->assertSame('Second\\Space', $byName['SecondTrait']->namespace);
        $this->assertSame('', $byName['GlobalClass']->namespace);
    }

    #[Test]
    #[TestDox('Handles semicolon and brace namespace syntax')]
    public function handlesNamespaceSyntax(): void
    {
        $code1 = '<?php
        namespace App\\Models;
        class User {}
        ';

        $result1 = $this->tokenizer->parse($code1);
        $this->assertCount(1, $result1);
        $this->assertSame('App\\Models', $result1[0]->namespace);

        $code2 = '<?php
        namespace App\\Controllers {
            class UserController {}
        }
        ';

        $result2 = $this->tokenizer->parse($code2);
        $this->assertCount(1, $result2);
        $this->assertSame('App\\Controllers', $result2[0]->namespace);
    }

    // ========================================
    // INTEGRATION TESTS
    // ========================================

    #[Test]
    #[TestDox('Full integration: complex codebase parsing')]
    public function fullIntegrationComplexCodebase(): void
    {
        $complexCode = '<?php

        declare(strict_types=1);

        namespace MyApp\\Core\\Database;

        use PDO;
        use Exception;
        use MyApp\\Contracts\\RepositoryInterface;

        /**
         * Complex repository with all features
         */
        #[Repository]
        #[Injectable(scope: "singleton")]
        abstract class AbstractRepository implements RepositoryInterface
        {
            protected PDO $connection;
            
            public function __construct(PDO $connection) {
                $this->connection = $connection;
            }
            
            abstract public function find(int $id): ?object;
        }

        namespace MyApp\\Repositories {
            use MyApp\\Core\\Database\\AbstractRepository;
            use MyApp\\Models\\User;
            
            final readonly class UserRepository extends AbstractRepository
            {
                public function find(int $id): ?User {
                    // Implementation
                    return null;
                }
                
                public function createUser(): User {
                    return new class extends User {
                        public function getType(): string {
                            return "anonymous";
                        }
                    };
                }
            }
        }

        namespace MyApp\\Contracts {
            interface RepositoryInterface {
                public function find(int $id): ?object;
            }
            
            interface CacheableInterface {
                public function getCacheKey(): string;
            }
        }

        namespace MyApp\\Traits {
            trait TimestampableTrait {
                protected ?\\DateTimeImmutable $createdAt = null;
                protected ?\\DateTimeImmutable $updatedAt = null;
            }
            
            trait CacheableTrait {
                public function getCacheKey(): string {
                    return static::class . ":" . $this->getId();
                }
            }
        }

        namespace MyApp\\Enums {
            enum UserStatus: string {
                case ACTIVE = "active";
                case INACTIVE = "inactive";
                case PENDING = "pending";
                case BANNED = "banned";
            }
            
            enum Priority: int {
                case LOW = 1;
                case MEDIUM = 5;
                case HIGH = 10;
                case CRITICAL = 20;
            }
        }

        namespace {
            class GlobalUtility {
                public static function helper(): string {
                    return "global";
                }
            }
        }
        ';

        $result = $this->tokenizer->parse($complexCode);

        // Should find all declarations
        $this->assertCount(9, $result);

        // Group by namespace
        $byNamespace = [];
        foreach ($result as $declaration) {
            $byNamespace[$declaration->namespace][] = $declaration;
        }

        // Check MyApp\Core\Database namespace
        $this->assertArrayHasKey('MyApp\\Core\\Database', $byNamespace);
        $this->assertCount(1, $byNamespace['MyApp\\Core\\Database']);
        $this->assertSame('AbstractRepository', $byNamespace['MyApp\\Core\\Database'][0]->name);
        $this->assertTrue($byNamespace['MyApp\\Core\\Database'][0]->isAbstract);

        // Check MyApp\Repositories namespace
        $this->assertArrayHasKey('MyApp\\Repositories', $byNamespace);
        $this->assertCount(1, $byNamespace['MyApp\\Repositories']);
        $this->assertSame('UserRepository', $byNamespace['MyApp\\Repositories'][0]->name);
        $this->assertTrue($byNamespace['MyApp\\Repositories'][0]->isFinal);
        $this->assertTrue($byNamespace['MyApp\\Repositories'][0]->isReadonly);

        // Check interfaces
        $this->assertArrayHasKey('MyApp\\Contracts', $byNamespace);
        $this->assertCount(2, $byNamespace['MyApp\\Contracts']);

        // Check traits
        $this->assertArrayHasKey('MyApp\\Traits', $byNamespace);
        $this->assertCount(2, $byNamespace['MyApp\\Traits']);

        // Check enums
        $this->assertArrayHasKey('MyApp\\Enums', $byNamespace);
        $this->assertCount(2, $byNamespace['MyApp\\Enums']);

        // Check global namespace
        $this->assertArrayHasKey('', $byNamespace);
        $this->assertCount(1, $byNamespace['']);
        $this->assertSame('GlobalUtility', $byNamespace[''][0]->name);
    }

    #[Test]
    #[TestDox('Integration: TokenizerInterface constants work with bitwise operations')]
    public function tokenizerInterfaceConstantsBitwise(): void
    {
        $code = '<?php
        class TestClass {}
        interface TestInterface {}
        trait TestTrait {}
        enum TestEnum {}
        ';

        // Test all individual constants
        $classes = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_CLASSES);
        $this->assertCount(1, $classes);
        $this->assertTrue($classes[0]->isClass);

        $interfaces = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_INTERFACES);
        $this->assertCount(1, $interfaces);
        $this->assertTrue($interfaces[0]->isInterface);

        $traits = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_TRAITS);
        $this->assertCount(1, $traits);
        $this->assertTrue($traits[0]->isTrait);

        $enums = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_ENUMS);
        $this->assertCount(1, $enums);
        $this->assertTrue($enums[0]->isEnum);

        // Test combinations
        $classesAndInterfaces = $this->tokenizer->parse($code,
            TokenizerInterface::SEARCH_CLASSES | TokenizerInterface::SEARCH_INTERFACES
        );
        $this->assertCount(2, $classesAndInterfaces);

        $all = $this->tokenizer->parse($code, TokenizerInterface::SEARCH_ALL);
        $this->assertCount(4, $all);

        // Test exclusions
        $allExceptEnums = $this->tokenizer->parse($code,
            TokenizerInterface::SEARCH_ALL & ~TokenizerInterface::SEARCH_ENUMS
        );
        $this->assertCount(3, $allExceptEnums);
    }

    #[Test]
    #[TestDox('Integration: ClassInfo properties with reflection')]
    public function classInfoPropertiesWithReflection(): void
    {
        // Create a class that will exist for reflection
        eval('class TestReflectionClass {}');

        $classInfo = new ClassInfo('TestReflectionClass', 'class');

        // Test exists
        $this->assertTrue($classInfo->exists());

        // Test reflector
        $reflector = $classInfo->reflector;
        $this->assertInstanceOf(\ReflectionClass::class, $reflector);
        $this->assertSame('TestReflectionClass', $reflector->getName());

        // Test virtual properties
        $this->assertSame('TestReflectionClass', $classInfo->name);
        $this->assertSame('', $classInfo->namespace);
        $this->assertTrue($classInfo->isClass);
        $this->assertTrue($classInfo->isConcrete);
    }

    #[Test]
    #[TestDox('Integration: Real PHP file parsing simulation')]
    public function realPhpFileParsingSimulation(): void
    {
        // Simulate a real PHP file with mixed content
        $realFileContent = '<?php

        // File header comment
        declare(strict_types=1);

        /**
         * @package MyApp
         * @author Developer
         */

        namespace MyApp\\Models;

        use DateTime;
        use JsonSerializable;

        /**
         * User model class
         * 
         * @entity
         * @table("users")
         */
        class User implements JsonSerializable
        {
            private int $id;
            private string $email;
            private DateTime $createdAt;

            public function __construct(int $id, string $email) {
                $this->id = $id;
                $this->email = $email;
                $this->createdAt = new DateTime();
            }

            public function jsonSerialize(): array {
                return [
                    "id" => $this->id,
                    "email" => $this->email,
                    "created_at" => $this->createdAt->format("Y-m-d H:i:s")
                ];
            }

            public function createProfile(): object {
                return new class($this) {
                    public function __construct(private User $user) {}
                    
                    public function getDisplayName(): string {
                        return $this->user->email;
                    }
                };
            }
        }

        // Another class in same namespace
        abstract class BaseModel 
        {
            abstract public function getId(): int;
        }

        // Trait for common functionality
        trait Timestampable 
        {
            protected ?DateTime $createdAt = null;
            protected ?DateTime $updatedAt = null;

            public function touch(): void {
                $this->updatedAt = new DateTime();
            }
        }
        ';

        $result = $this->tokenizer->parse($realFileContent);

        $this->assertCount(3, $result);

        $names = array_map(fn($d) => $d->name, $result);
        $this->assertContains('User', $names);
        $this->assertContains('BaseModel', $names);
        $this->assertContains('Timestampable', $names);

        // All should be in MyApp\Models namespace
        foreach ($result as $declaration) {
            $this->assertSame('MyApp\\Models', $declaration->namespace);
        }

        // Check specific properties
        $user = null;
        $baseModel = null;
        $timestampable = null;

        foreach ($result as $declaration) {
            if ($declaration->name === 'User') $user = $declaration;
            if ($declaration->name === 'BaseModel') $baseModel = $declaration;
            if ($declaration->name === 'Timestampable') $timestampable = $declaration;
        }

        $this->assertNotNull($user);
        $this->assertNotNull($baseModel);
        $this->assertNotNull($timestampable);

        $this->assertTrue($user->isClass);
        $this->assertTrue($user->isConcrete);
        $this->assertFalse($user->isAbstract);

        $this->assertTrue($baseModel->isClass);
        $this->assertFalse($baseModel->isConcrete);
        $this->assertTrue($baseModel->isAbstract);

        $this->assertTrue($timestampable->isTrait);
        $this->assertFalse($timestampable->isClass);
    }

    #[Test]
    #[TestDox('Integration: Error handling across components')]
    public function errorHandlingAcrossComponents(): void
    {
        // Test graceful handling of various error conditions
        $errorCodes = [
            '<?php namespace ; class Test {}', // Invalid namespace
            '<?php class {}', // Missing class name
            '<?php namespace Test class NoSemicolon {}', // Missing semicolon
            '<?php abstract final interface Test {}', // Invalid modifiers
        ];

        foreach ($errorCodes as $code) {
            $result = $this->tokenizer->parse($code);
            $this->assertIsArray($result);
            // Should not throw exceptions, may return empty or partial results
        }
    }

    #[Test]
    #[TestDox('Integration: Performance with realistic codebase')]
    public function performanceWithRealisticCodebase(): void
    {
        // Generate realistic codebase structure
        $code = '<?php';

        $namespaces = ['App\\Models', 'App\\Controllers', 'App\\Services', 'App\\Repositories'];
        $classTypes = ['class', 'interface', 'trait', 'enum'];

        foreach ($namespaces as $namespace) {
            $code .= "\n\nnamespace {$namespace};";

            for ($i = 1; $i <= 25; $i++) { // 25 classes per namespace
                $type = $classTypes[array_rand($classTypes)];
                $name = ucfirst($type) . $i;

                switch ($type) {
                    case 'class':
                        $modifiers = ['', 'abstract ', 'final ', 'readonly '];
                        $modifier = $modifiers[array_rand($modifiers)];
                        $code .= "\n{$modifier}class {$name} {}";
                        break;
                    case 'interface':
                        $code .= "\ninterface {$name} {}";
                        break;
                    case 'trait':
                        $code .= "\ntrait {$name} {}";
                        break;
                    case 'enum':
                        $code .= "\nenum {$name} {}";
                        break;
                }
            }
        }

        $startTime = microtime(true);
        $result = $this->tokenizer->parse($code);
        $endTime = microtime(true);

        $this->assertCount(100, $result); // 4 namespaces × 25 classes = 100
        $this->assertLessThan(2.0, $endTime - $startTime, 'Performance test failed');

        // Verify distribution
        $byNamespace = [];
        foreach ($result as $declaration) {
            $byNamespace[$declaration->namespace][] = $declaration;
        }

        foreach ($namespaces as $namespace) {
            $this->assertArrayHasKey($namespace, $byNamespace);
            $this->assertCount(25, $byNamespace[$namespace]);
        }
    }

    // ========================================
    // CRITICAL EDGE CASES TESTS
    // ========================================

    #[Test]
    #[TestDox('Handles invalid PHP gracefully')]
    public function handlesInvalidPhp(): void
    {
        // Completely broken PHP
        $invalidCodes = [
            'completely invalid php code ###',
            '<?php class {', // Missing class name
            '<?php namespace;', // Empty namespace
            '<?php abstract interface Test {}', // Invalid combination
            '<?php final trait Test {}', // Invalid combination
            '<?php class class {}', // Keyword as class name won't work
        ];

        foreach ($invalidCodes as $code) {
            $result = $this->tokenizer->parse($code);
            $this->assertIsArray($result, "Failed for code: {$code}");
            // Should not throw exceptions, might return empty array
        }
    }

    #[Test]
    #[TestDox('Handles very large PHP files')]
    public function handlesVeryLargeFiles(): void
    {
        // Generate a large PHP file (10MB+)
        $code = '<?php namespace LargeTest;' . PHP_EOL;

        $classCount = 5000; // Should generate ~10MB+ file
        for ($i = 1; $i <= $classCount; $i++) {
            $code .= "class LargeClass{$i} { public function method{$i}() { return {$i}; } }" . PHP_EOL;
        }

        $startTime = microtime(true);
        $result = $this->tokenizer->parse($code);
        $endTime = microtime(true);

        $this->assertCount($classCount, $result);
        $this->assertLessThan(5.0, $endTime - $startTime, 'Parsing took too long for large file');

        // Verify random samples
        $this->assertSame('LargeClass1', $result[0]->name);
        $this->assertSame("LargeClass{$classCount}", $result[$classCount - 1]->name);
        $this->assertSame('LargeTest', $result[0]->namespace);
    }

    #[Test]
    #[TestDox('Handles deeply nested namespace expressions')]
    public function handlesDeeplyNestedNamespaces(): void
    {
        // Create very deep namespace
        $deepNamespace = implode('\\', array_fill(0, 100, 'Level'));
        $code = "<?php namespace {$deepNamespace}; class DeepClass {}";

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('DeepClass', $result[0]->name);
        $this->assertSame($deepNamespace, $result[0]->namespace);
        $this->assertSame($deepNamespace . '\\DeepClass', $result[0]->fullQualifiedName);
    }

    #[Test]
    #[TestDox('Handles classes with extremely long names')]
    public function handlesExtremelyLongNames(): void
    {
        $longName = str_repeat('VeryLongClassName', 100); // 17 * 100 = 1700 chars
        $code = "<?php class {$longName} {}";

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame($longName, $result[0]->name);
        $this->assertSame(1700, strlen($result[0]->name));
    }

    #[Test]
    #[TestDox('Handles binary and special characters in source')]
    public function handlesBinaryAndSpecialChars(): void
    {
        // UTF-8 BOM + special characters
        $code = "\xEF\xBB\xBF<?php\n// Special chars: ñáéíóú\nclass TestClass {}\n/* 中文注释 */";

        $result = $this->tokenizer->parse($code);

        $this->assertCount(1, $result);
        $this->assertSame('TestClass', $result[0]->name);
    }

    #[Test]
    #[TestDox('Handles all modifier combinations')]
    public function handlesAllModifierCombinations(): void
    {
        $code = '<?php
        abstract class AbstractOnly {}
        final class FinalOnly {}
        readonly class ReadonlyOnly {}
        abstract readonly class AbstractReadonly {}
        final readonly class FinalReadonly {}
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(5, $result);

        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        // Abstract only
        $this->assertTrue($byName['AbstractOnly']->isAbstract);
        $this->assertFalse($byName['AbstractOnly']->isFinal);
        $this->assertFalse($byName['AbstractOnly']->isReadonly);

        // Final only
        $this->assertFalse($byName['FinalOnly']->isAbstract);
        $this->assertTrue($byName['FinalOnly']->isFinal);
        $this->assertFalse($byName['FinalOnly']->isReadonly);

        // Readonly only
        $this->assertFalse($byName['ReadonlyOnly']->isAbstract);
        $this->assertFalse($byName['ReadonlyOnly']->isFinal);
        $this->assertTrue($byName['ReadonlyOnly']->isReadonly);

        // Abstract + Readonly
        $this->assertTrue($byName['AbstractReadonly']->isAbstract);
        $this->assertFalse($byName['AbstractReadonly']->isFinal);
        $this->assertTrue($byName['AbstractReadonly']->isReadonly);

        // Final + Readonly
        $this->assertFalse($byName['FinalReadonly']->isAbstract);
        $this->assertTrue($byName['FinalReadonly']->isFinal);
        $this->assertTrue($byName['FinalReadonly']->isReadonly);
    }

    #[Test]
    #[TestDox('Handles complex PHP 8.4 features')]
    public function handlesPhp84Features(): void
    {
        $code = '<?php
        class PropertyHooksClass {
            public string $name {
                get => strtoupper($this->name);
                set(string $value) => $this->name = trim($value);
            }
            
            public readonly string $computed {
                get => $this->calculateValue();
            }
        }
        
        #[Attribute]
        class MyAttribute {
            public function __construct(
                public readonly string $value = "default"
            ) {}
        }
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(2, $result);

        $names = array_map(fn($d) => $d->name, $result);
        $this->assertContains('PropertyHooksClass', $names);
        $this->assertContains('MyAttribute', $names);
    }

    #[Test]
    #[TestDox('Handles memory stress test')]
    public function handlesMemoryStressTest(): void
    {
        $startMemory = memory_get_usage(true);

        // Create many classes to test memory usage
        $code = '<?php';
        for ($i = 0; $i < 1000; $i++) {
            $code .= "\nnamespace Stress{$i}; class StressClass{$i} {}";
        }

        $result = $this->tokenizer->parse($code);

        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;

        $this->assertCount(1000, $result);
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage too high: ' . ($memoryUsed / 1024 / 1024) . 'MB');
    }

    #[Test]
    #[TestDox('Handles concurrent namespace switches')]
    public function handlesConcurrentNamespaceSwitches(): void
    {
        $code = '<?php
        namespace A; class A1 {}
        namespace B; class B1 {}
        namespace A; class A2 {}
        namespace C; class C1 {}
        namespace B; class B2 {}
        namespace A; class A3 {}
        ';

        $result = $this->tokenizer->parse($code);

        $this->assertCount(6, $result);

        $expected = [
            'A1' => 'A', 'A2' => 'A', 'A3' => 'A',
            'B1' => 'B', 'B2' => 'B',
            'C1' => 'C'
        ];

        foreach ($result as $declaration) {
            $this->assertArrayHasKey($declaration->name, $expected);
            $this->assertSame($expected[$declaration->name], $declaration->namespace);
        }
    }

    #[Test]
    #[TestDox('Handles modifier reset between declarations')]
    public function handlesModifierResetBetweenDeclarations(): void
    {
        $code = '<?php
        abstract final class BothModifiers {} // both flags should be set
        readonly class ValidReadonly {}
        class PlainClass {} // should not inherit modifiers from previous
        abstract class ValidAbstract {}
        use SomeNamespace\SomeTrait; // use statement should reset modifiers
        final class AfterUse {}
        ';

        $result = $this->tokenizer->parse($code);

        $byName = [];
        foreach ($result as $declaration) {
            $byName[$declaration->name] = $declaration;
        }

        // BothModifiers should have both abstract and final
        $this->assertTrue($byName['BothModifiers']->isAbstract);
        $this->assertTrue($byName['BothModifiers']->isFinal);

        // ValidReadonly should only be readonly
        $this->assertFalse($byName['ValidReadonly']->isAbstract);
        $this->assertFalse($byName['ValidReadonly']->isFinal);
        $this->assertTrue($byName['ValidReadonly']->isReadonly);

        // PlainClass should have no modifiers
        $this->assertFalse($byName['PlainClass']->isAbstract);
        $this->assertFalse($byName['PlainClass']->isFinal);
        $this->assertFalse($byName['PlainClass']->isReadonly);

        // ValidAbstract should only be abstract
        $this->assertTrue($byName['ValidAbstract']->isAbstract);
        $this->assertFalse($byName['ValidAbstract']->isFinal);
        $this->assertFalse($byName['ValidAbstract']->isReadonly);

        // AfterUse should only be final (modifiers reset by use statement)
        $this->assertFalse($byName['AfterUse']->isAbstract);
        $this->assertTrue($byName['AfterUse']->isFinal);
        $this->assertFalse($byName['AfterUse']->isReadonly);
    }

    // ========================================
    // SECURITY AND STRESS TESTS
    // ========================================

    #[Test]
    #[TestDox('Handles malicious code injection attempts')]
    public function handlesMaliciousCodeInjection(): void
    {
        // Test various injection attempts
        $maliciousCodes = [
            '<?php eval("system(\'rm -rf /\')"); class Test {}',
            '<?php /* <?php */ class Test {}',
            '<?php namespace App; exec("dangerous command"); class Test {}',
            '<?php class Test { public function __destruct() { system("evil"); } }',
            '<?php ?><?php class Test {}<script>alert("xss")</script>',
        ];

        foreach ($maliciousCodes as $code) {
            $result = $this->tokenizer->parse($code);

            // Should parse the class structure without executing malicious code
            $this->assertIsArray($result);

            // Should find the Test class
            $foundTest = false;
            foreach ($result as $declaration) {
                if ($declaration->name === 'Test') {
                    $foundTest = true;
                    break;
                }
            }

            if (str_contains($code, 'class Test')) {
                $this->assertTrue($foundTest, "Failed to parse Test class from: " . substr($code, 0, 50));
            }
        }
    }

    #[Test]
    #[TestDox('Handles memory exhaustion attempts')]
    public function handlesMemoryExhaustionAttempts(): void
    {
        $startMemory = memory_get_usage(true);

        // Create code with many large strings to test memory handling
        $code = '<?php namespace MemoryTest;';

        // Add many classes with large doc comments
        for ($i = 0; $i < 100; $i++) {
            $largeComment = str_repeat('* Very long comment line that repeats many times ', 1000);
            $code .= "\n/**\n{$largeComment}\n*/\nclass MemoryClass{$i} {}";
        }

        $result = $this->tokenizer->parse($code);

        $endMemory = memory_get_usage(true);
        $memoryIncrease = $endMemory - $startMemory;

        $this->assertCount(100, $result);
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory usage too high: ' . ($memoryIncrease / 1024 / 1024) . 'MB');
    }

    #[Test]
    #[TestDox('Handles infinite loop attempts')]
    public function handlesInfiniteLoopAttempts(): void
    {
        // Code that might cause infinite loops in naive parsers
        $trickyCodes = [
            '<?php namespace A\\B\\C\\D\\E\\F\\G\\H\\I\\J\\K\\L\\M\\N\\O\\P\\Q\\R\\S\\T\\U\\V\\W\\X\\Y\\Z; class Test {}',
            '<?php /* nested /* comments */ */ class Test {}',
            '<?php "string with namespace keyword: namespace Test;" class RealClass {}',
            '<?php class Test { public function method() { $code = "class FakeClass {}"; } }',
        ];

        foreach ($trickyCodes as $code) {
            $startTime = microtime(true);

            $result = $this->tokenizer->parse($code);

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $this->assertLessThan(1.0, $duration, "Parsing took too long for: " . substr($code, 0, 50));
            $this->assertIsArray($result);
        }
    }

    #[Test]
    #[TestDox('Handles deeply nested structures without stack overflow')]
    public function handlesDeeplyNestedStructures(): void
    {
        // Create deeply nested anonymous classes
        $code = '<?php class OuterClass {';

        $depth = 500; // Deep nesting
        for ($i = 0; $i < $depth; $i++) {
            $code .= "public function level{$i}() { return new class {";
        }

        // Close all braces
        for ($i = 0; $i < $depth; $i++) {
            $code .= '}; }';
        }
        $code .= '}';

        $result = $this->tokenizer->parse($code);

        // Should only find the named class, not crash
        $this->assertCount(1, $result);
        $this->assertSame('OuterClass', $result[0]->name);
    }

    #[Test]
    #[TestDox('Handles unicode and encoding attacks')]
    public function handlesUnicodeAndEncodingAttacks(): void
    {
        $unicodeCodes = [
            // UTF-8 BOM + normal class
            "\xEF\xBB\xBF<?php class BomClass {}",
            // Unicode characters in class names (valid in PHP)
            "<?php class Классы {}",
            // Unicode zero-width characters
            "<?php class Test\u{200B}Class {}",
            // Mixed encodings
            "<?php /* комментарий */ class EncodingTest {}",
        ];

        foreach ($unicodeCodes as $code) {
            $result = $this->tokenizer->parse($code);
            $this->assertIsArray($result);
            $this->assertGreaterThanOrEqual(1, count($result));
        }
    }

    #[Test]
    #[TestDox('Stress test: parsing extremely large codebase')]
    public function stressTestExtremeleLargeCodebase(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Generate massive codebase
        $namespaceCount = 50;
        $classesPerNamespace = 200;
        $totalClasses = $namespaceCount * $classesPerNamespace; // 10,000 classes

        $code = '<?php';

        for ($ns = 1; $ns <= $namespaceCount; $ns++) {
            $code .= "\n\nnamespace StressTest\\Level{$ns};";

            for ($cls = 1; $cls <= $classesPerNamespace; $cls++) {
                $modifiers = ['', 'abstract ', 'final ', 'readonly '];
                $modifier = $modifiers[$cls % 4];

                $code .= "\n{$modifier}class StressClass{$ns}_{$cls} {";
                $code .= "public function method{$cls}() { return {$cls}; }";
                $code .= '}';
            }
        }

        $result = $this->tokenizer->parse($code);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->assertCount($totalClasses, $result);
        $this->assertLessThan(10.0, $duration, "Stress test took too long: {$duration}s");
        $this->assertLessThan(200 * 1024 * 1024, $memoryUsed, 'Memory usage too high: ' . ($memoryUsed / 1024 / 1024) . 'MB');

        // Verify structure
        $namespaces = [];
        foreach ($result as $declaration) {
            $namespaces[$declaration->namespace][] = $declaration;
        }

        $this->assertCount($namespaceCount, $namespaces);

        foreach ($namespaces as $namespace => $classes) {
            $this->assertCount($classesPerNamespace, $classes);
        }
    }

    #[Test]
    #[TestDox('Security: Information disclosure prevention')]
    public function securityInformationDisclosurePrevention(): void
    {
        // Test that errors don't leak sensitive information
        $sensitiveCode = '<?php 
        // Sensitive comment with password: secret123
        class DatabaseConfig {
            private $password = "super_secret_password";
            private $apiKey = "sk-1234567890abcdef";
        }
        ';

        $result = $this->tokenizer->parse($sensitiveCode);

        $this->assertCount(1, $result);
        $this->assertSame('DatabaseConfig', $result[0]->name);

        // ClassInfo should not expose source code content in its properties
        $classInfo = $result[0];

        // Check all public properties for sensitive data
        $this->assertStringNotContainsString('secret123', $classInfo->fullQualifiedName);
        $this->assertStringNotContainsString('super_secret_password', $classInfo->fullQualifiedName);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $classInfo->fullQualifiedName);

        $this->assertStringNotContainsString('secret123', $classInfo->name);
        $this->assertStringNotContainsString('super_secret_password', $classInfo->name);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $classInfo->name);

        $this->assertStringNotContainsString('secret123', $classInfo->namespace);
        $this->assertStringNotContainsString('super_secret_password', $classInfo->namespace);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $classInfo->namespace);

        $this->assertStringNotContainsString('secret123', $classInfo->type);
        $this->assertStringNotContainsString('super_secret_password', $classInfo->type);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $classInfo->type);

        // Test that ClassInfo doesn't exist (as expected for parsed-only classes)
        $this->assertFalse($classInfo->exists());

        // Test that accessing non-existent reflector throws expected exception
        try {
            $reflector = $classInfo->reflector;
            $this->fail('Expected ReflectionException was not thrown');
        } catch (\ReflectionException $e) {
            // Expected behavior - should throw exception for non-existent class
            $this->assertStringContainsString('Class DatabaseConfig does not exist', $e->getMessage());
            // But exception message should not contain sensitive information
            $this->assertStringNotContainsString('secret123', $e->getMessage());
            $this->assertStringNotContainsString('super_secret_password', $e->getMessage());
            $this->assertStringNotContainsString('sk-1234567890abcdef', $e->getMessage());
        }
    }

    #[Test]
    #[TestDox('Concurrent parsing simulation')]
    public function concurrentParsingSimulation(): void
    {
        // Simulate concurrent parsing of different code snippets
        $codes = [];
        for ($i = 1; $i <= 50; $i++) {
            $codes[] = "<?php namespace Concurrent$i; class ConcurrentClass{$i} {}";
        }

        $results = [];
        $startTime = microtime(true);

        // Simulate concurrent processing
        foreach ($codes as $index => $code) {
            $results[$index] = $this->tokenizer->parse($code);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Verify all results
        $this->assertCount(50, $results);
        $this->assertLessThan(2.0, $duration, "Concurrent simulation took too long: {$duration}s");

        foreach ($results as $index => $result) {
            $this->assertCount(1, $result);
            $this->assertSame("ConcurrentClass" . ($index + 1), $result[0]->name);
            $this->assertSame("Concurrent" . ($index + 1), $result[0]->namespace);
        }
    }

    #[Test]
    #[TestDox('Resource cleanup verification')]
    public function resourceCleanupVerification(): void
    {
        $initialMemory = memory_get_usage(true);

        // Create and destroy many tokenizer instances
        for ($i = 0; $i < 100; $i++) {
            $tokenizer = new Tokenizer();
            $result = $tokenizer->parse("<?php class Cleanup$i {}");
            $this->assertCount(1, $result);

            // Force cleanup
            unset($tokenizer, $result);
        }

        // Force garbage collection
        gc_collect_cycles();

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be minimal
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease,
            'Memory leak detected: ' . ($memoryIncrease / 1024 / 1024) . 'MB increase');
    }

    #[Test]
    #[TestDox('Edge case: Maximum PHP limits')]
    public function edgeCaseMaximumPhpLimits(): void
    {
        // Test near PHP limits
        $maxNestingLevel = 100; // Conservative limit
        $maxNameLength = 1000;  // Conservative limit

        // Test maximum nesting
        $deepNamespace = implode('\\', array_fill(0, $maxNestingLevel, 'Deep'));
        $code = "<?php namespace $deepNamespace; class DeepClass {}";

        $result = $this->tokenizer->parse($code);
        $this->assertCount(1, $result);
        $this->assertSame('DeepClass', $result[0]->name);

        // Test maximum name length
        $longName = str_repeat('Long', $maxNameLength / 4);
        $code2 = "<?php class $longName {}";

        $result2 = $this->tokenizer->parse($code2);
        $this->assertCount(1, $result2);
        $this->assertSame($longName, $result2[0]->name);
    }
}
