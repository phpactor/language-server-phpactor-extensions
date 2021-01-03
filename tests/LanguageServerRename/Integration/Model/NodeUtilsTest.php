<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Integration\Model;

use Generator;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\TestUtils\ExtractOffset;

class NodeUtilsTest extends TestCase
{
    /**
     * @dataProvider provideNames
     */
    public function testNameToken(string $source, Token $expectedToken, string $expectedText, ?Range $expectedRange): void
    {
        [$source, $offset] = ExtractOffset::fromSource($source);

        $parser = new Parser();
        $root = $parser->parseSourceFile($source);
        
        $utils = new NodeUtils();
        $node = $root->getDescendantNodeAtPosition((int)$offset);
        $foundToken = $utils->getNodeNameToken($node, ltrim($expectedText, '$'));
        
        $this->assertEquals($expectedToken, $foundToken, 'length');
        $this->assertEquals($foundToken->getText($source), $expectedText, 'text');
        if ($expectedRange !== null) {
            $actualRange = $utils->getNodeNameRange($node, ltrim($expectedText, '$'));
            $this->assertEquals($expectedRange, $actualRange);
        }
    }

    public function provideNames(): Generator
    {
        yield 'Class' => [
            '<?php class MyCla<>ss {}',
            new Token(TokenKind::Name, 11, 12, 8),
            "MyClass",
            null
        ];

        yield 'Interface' => [
            '<?php interface MyCla<>ss {}',
            new Token(TokenKind::Name, 15, 16, 8),
            "MyClass",
            null
        ];

        yield 'Trait' => [
            '<?php trait MyCla<>ss {}',
            new Token(TokenKind::Name, 11, 12, 8),
            "MyClass",
            null
        ];

        yield 'Extends class' => [
            '<?php class Class1 extends MyCla<>ss {}',
            new Token(TokenKind::Name, 26, 27, 8),
            "MyClass",
            null
        ];

        yield 'Implements interface' => [
            '<?php class Class1 implements MyCla<>ss {}',
            new Token(TokenKind::Name, 29, 30, 8),
            "MyClass",
            null
        ];

        yield 'Implements interface 2' => [
            '<?php class Class1 implements OtherClass, MyCla<>ss {}',
            new Token(TokenKind::Name, 41, 42, 8),
            "MyClass",
            null
        ];

        yield 'Uses trait' => [
            '<?php class Class1 { use MyTra<>it; }',
            new Token(TokenKind::Name, 24, 25, 8),
            "MyTrait",
            null
        ];

        yield 'Method' => [
            '<?php class MyClass { function myMe<>thod() {} }',
            new Token(TokenKind::Name, 30, 31, 9),
            "myMethod",
            null
        ];

        yield 'Type hint' => [
            '<?php class MyClass { function myMethod(\A\B<>\C\D $param1) {} }',
            new Token(TokenKind::Name, 47, 47, 1),
            "D",
            null
        ];

        yield 'Static constant' => [
            '<?php class MyClass { function myMethod() { self::SOM<>E_CONST = 5; } }',
            new Token(TokenKind::Name, 50, 50, 10),
            "SOME_CONST",
            null
        ];

        yield 'Static variable' => [
            '<?php class MyClass { function myMethod() { self::$some<>Var = 5; } }',
            new Token(TokenKind::VariableName, 50, 50, 8),
            '$someVar',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 51],
                'end' => ['line' => 0, 'character' => 58],
            ])
        ];

        yield 'Property' => [
            '<?php class MyClass { public $myP<>rop; }',
            new Token(TokenKind::VariableName, 28, 29, 8),
            '$myProp',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 30],
                'end' => ['line' => 0, 'character' => 36],
            ])
        ];

        yield 'Property multiple' => [
            '<?php class MyClass { public $myP<>rop, $otherProp; }',
            new Token(TokenKind::VariableName, 28, 29, 8),
            '$myProp',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 30],
                'end' => ['line' => 0, 'character' => 36],
            ])
        ];

        yield 'Property multiple (Second property)' => [
            '<?php class MyClass { public $otherProp, $myP<>rop; }',
            new Token(TokenKind::VariableName, 40, 41, 8),
            '$myProp',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 42],
                'end' => ['line' => 0, 'character' => 48],
            ])
        ];

        yield 'Const' => [
            '<?php class MyClass { const MY_<>CONST; }',
            new Token(TokenKind::Name, 27, 28, 9),
            'MY_CONST',
            null
        ];

        yield 'Const multiple' => [
            '<?php class MyClass { const MY_<>CONST, OTHER_CONST; }',
            new Token(TokenKind::Name, 27, 28, 9),
            'MY_CONST',
            null
        ];

        yield 'Const multiple (Second constant)' => [
            '<?php class MyClass { const MY_CONST, OTH<>ER_CONST; }',
            new Token(TokenKind::Name, 37, 38, 12),
            'OTHER_CONST',
            null
        ];

        yield 'Parameter' => [
            '<?php function f($ar<>g1)',
            new Token(TokenKind::VariableName, 17, 17, 5),
            '$arg1',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 18],
                'end' => ['line' => 0, 'character' => 22],
            ])
        ];

        yield 'Variable' => [
            '<?php function f() { $va<>r1 = 10; }',
            new Token(TokenKind::VariableName, 20, 21, 6),
            '$var1',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 22],
                'end' => ['line' => 0, 'character' => 26],
            ])
        ];

        yield 'Variable double nested' => [
            '<?php function f() { <>$$var1 = 10; }',
            new Token(TokenKind::VariableName, 22, 22, 5),
            '$var1',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 23],
                'end' => ['line' => 0, 'character' => 27],
            ])
        ];

        yield 'Member access' => [
            '<?php class MyClass { function myMethod() { $this->other<>Method(); } }',
            new Token(TokenKind::Name, 51, 51, 11),
            'otherMethod',
            null
        ];

        yield 'Property access' => [
            '<?php class MyClass { private function myMethod() { $this-><>otherProperty; } }',
            new Token(TokenKind::Name, 59, 59, 13),
            'otherProperty',
            null
        ];

        yield 'Named property access' => [
            '<?php class MyClass { pub<>lic $otherProp, $myProp; } }',
            new Token(TokenKind::VariableName, 28, 29, 11),
            '$otherProp',
            Range::fromArray([
                'start' => ['line' => 0, 'character' => 30],
                'end' => ['line' => 0, 'character' => 39],
            ])
        ];
    }
}
