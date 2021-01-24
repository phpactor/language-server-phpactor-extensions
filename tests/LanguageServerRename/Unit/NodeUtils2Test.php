<?php

// namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

// use Generator;
// use InvalidArgumentException;
// use Microsoft\PhpParser\Parser;
// use Microsoft\PhpParser\Token;
// use Microsoft\PhpParser\TokenKind;
// use PHPUnit\Framework\TestCase;
// use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
// use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
// use Phpactor\TextDocument\ByteOffsetRange;

// class NodeUtils2Test extends TestCase
// {
//     /** @dataProvider provideNames */
//     public function testNameToken(string $source, array $tokenKinds, array $expectedNames): void
//     {
//         $extractor = new OffsetExtractor();
//         $extractor->registerPoint("selection", "<>");
//         $extractor->registerPoint("tokenPositions", "|");
//         $extractor->registerRange("nameRanges", "{{", "}}", function (int $start, int $end) {
//             return ByteOffsetRange::fromInts($start, $end);
//         });
//         [
//             'selection'=> [ $selection ],
//             'tokenPositions' => $tokenPositions,
//             'nameRanges' => $expectedNameRanges,
//             'newSource' => $newSource
//         ] = $extractor->parse($source);

//         if ($tokenPositions % 3 == 0) {
//             throw new InvalidArgumentException("The number of token positions in a test should be divisible by 3. For each token we need: fullStart, start, end");
//         }

//         $expectedTokens = [];
//         for ($i = 0; $i < count($tokenPositions); $i += 3) {
//             $expectedTokens[] = new Token(
//                 $tokenKinds[(int)($i / 3)],
//                 $tokenPositions[$i + 0],
//                 $tokenPositions[$i + 1],
//                 $tokenPositions[$i + 2] - $tokenPositions[$i + 0]
//             );
//         }

//         $parser = new Parser();
//         $rootNode = $parser->parseSourceFile($newSource);
//         $node = $rootNode->getDescendantNodeAtPosition($selection);

//         $utils = new NodeUtils();
        
//         $this->assertEquals($expectedTokens, $utils->getNodeNameTokens($node), "Tokens:");
//         $this->assertEquals($expectedNameRanges, $utils->getNodeNameRanges($node), "Name ranges:");
//         $this->assertEquals($expectedNames, $utils->getNodeNameTexts($node), "Name texts:");
//     }
    
//     public function provideNames(): Generator
//     {
//         yield 'Class' => [
//             '<?php class| |{{MyCla<>ss}}| {}',
//             [ TokenKind::Name ],
//             [ 'MyClass' ]
//         ];

//         yield 'Interface' => [
//             '<?php interface| |{{MyInter<>face}}| {}',
//             [ TokenKind::Name ],
//             [ "MyInterface" ],
//         ];
        
//         yield 'Trait' => [
//             '<?php trait| |{{MyCla<>ss}}| {}',
//             [ TokenKind::Name ],
//             [ "MyClass" ],
//         ];

//         yield 'Extends class' => [
//             '<?php class Class1 extends| |{{MyCla<>ss}}| {}',
//             [ TokenKind::QualifiedName ],
//             ["MyClass"],
//         ];

//         yield 'Implements interface' => [
//             '<?php class Class1 implements| |{{MyCla<>ss}}| {}',
//             [ TokenKind::QualifiedName ],
//             ["MyClass"],
//         ];

//         yield 'Implements interface 2' => [
//             '<?php class Class1 implements OtherClass,| |{{MyCla<>ss}}| {}',
//             [ TokenKind::QualifiedName ],
//             ["MyClass"],
//         ];
        
//         yield 'Uses trait' => [
//             '<?php class Class1 { use| |{{MyTra<>it}}|; }',
//             [ TokenKind::QualifiedName ],
//             ["MyTrait"],
//         ];
        
//         yield 'Method' => [
//             '<?php class MyClass { function| |{{myMe<>thod}}|() {} }',
//             [ TokenKind::Name ],
//             ["myMethod"],
//         ];

//         yield 'Type hint' => [
//             '<?php class MyClass { function myMethod(||{{\A\B<>\C\D}}|| |${{param1}}|) {} }',
//             [ TokenKind::QualifiedName, TokenKind::VariableName ],
//             ["\A\B\C\D", "param1"],
//         ];
        
//         yield 'Static constant' => [
//             '<?php class MyClass { function myMethod() { self::||{{SOM<>E_CONST}}| = 5; } }',
//             [ TokenKind::Name ],
//             ["SOME_CONST"],
//         ];
        
//         yield 'Static variable' => [
//             '<?php class MyClass { function myMethod() { self::||${{some<>Var}}| = 5; } }',
//             [ TokenKind::VariableName ],
//             ['someVar']
//         ];
        
//         yield 'Property' => [
//             '<?php class MyClass { public| |${{myP<>rop}}|; }',
//             [ TokenKind::VariableName ],
//             ['myProp'],
//         ];
        
//         yield 'Property multiple' => [
//             '<?php class MyClass { public| |${{myP<>rop}}|, $otherProp; }',
//             [ TokenKind::VariableName ],
//             ['myProp'],
//         ];
        
//         yield 'Property multiple (Second property)' => [
//             '<?php class MyClass { public $otherProp,| |${{myP<>rop}}|; }',
//             [ TokenKind::VariableName ],
//             ['myProp'],
//         ];
        
//         yield 'Property multiple, in front' => [
//             '<?php class MyClass { <>public| |${{myProp}}|,| |${{otherProp}}|; }',
//             [ TokenKind::VariableName, TokenKind::VariableName ],
//             ['myProp', 'otherProp'],
//         ];

//         yield 'Const' => [
//             '<?php class MyClass { const| |{{MY_<>CONST}}|; }',
//             [ TokenKind::Name ],
//             ['MY_CONST'],
//         ];

//         yield 'Const multiple' => [
//             '<?php class MyClass { const| |{{MY_<>CONST}}|, OTHER_CONST; }',
//             [ TokenKind::Name ],
//             ['MY_CONST'],
//         ];

//         yield 'Const multiple (Second constant)' => [
//             '<?php class MyClass { const MY_CONST,| |{{OTH<>ER_CONST}}|; }',
//             [ TokenKind::Name ],
//             ['OTHER_CONST'],
//         ];

//         yield 'Parameter with type hint' => [
//             '<?php function f(<>||{{Type1}}|| |${{arg1}}|)',
//             [ TokenKind::QualifiedName, TokenKind::VariableName ],
//             [ 'Type1', 'arg1'],
//         ];
        
//         yield 'Parameter with no type hint' => [
//             '<?php function f(||${{ar<>g1}}|)',
//             [ TokenKind::VariableName ],
//             ['arg1'],
//         ];
        
//         yield 'Variable' => [
//             '<?php function f() {| |${{va<>r1}}| = 10; }',
//             [ TokenKind::VariableName ],
//             ['var1'],
//         ];

//         yield 'Variable double nested' => [
//             '<?php function f() { <>$||${{var1}}| = 10; }',
//             [ TokenKind::VariableName ],
//             ['var1'],
//         ];
        
//         yield 'Member access' => [
//             '<?php class MyClass { function myMethod() { $this->||{{other<>Method}}|(); } }',
//             [ TokenKind::Name ],
//             ['otherMethod'],
//         ];
        
//         yield 'Property access' => [
//             '<?php class MyClass { private function myMethod() { $this->||{{<>otherProperty}}|; } }',
//             [ TokenKind::Name ],
//             ['otherProperty'],
//         ];

//         yield 'Foreach array' => [
//             '<?php $value = 2; <>foreach($array as| |${{key}}| =>| |${{value}}|) { }',
//             [ TokenKind::VariableName, TokenKind::VariableName ],
//             ['key', 'value'],
//         ];

//         yield 'Array deconstruction with key' => [
//             '<?php [ <>"key"=>||${{var1}}| ] = someFuncReturningArray();',
//             [ TokenKind::VariableName ],
//             ['var1']
//         ];

//         yield 'Array deconstruction with key (nested)' => [
//             '<?php [ [<>"key"=>||${{var1}}| ] ] = someFuncReturningArray();',
//             [ TokenKind::VariableName ],
//             ['var1']
//         ];
//     }
// }
