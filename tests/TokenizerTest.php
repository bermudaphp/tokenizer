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
#[CoversClass(TokenizerInterface::class)]
class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

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
}
