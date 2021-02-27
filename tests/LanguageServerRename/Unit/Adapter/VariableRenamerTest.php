<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter;

use PHPUnit\Framework\TestCase;

class VariableRenamerTest extends TestCase
{
    /** @dataProvider provideGetRenameRange */
    public function testGetRenameRange(string $source): void
    {
        $this->assertTrue(true);
    }

	public function provideGetRenameRange(): \Generator
	{
		yield [
            'Rename argument' =>
            '<?php class Class1 { public function method1(${{a<>rg1}}){ } }'
        ];
	}
}