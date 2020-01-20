<?php
require_once __DIR__.'/../CSSParser.php';

class CSSColorTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider fromRGBProvider
	 **/
	public function testFromRGB($aRGB, $aExpectedColor, $aExpectedDescription)
	{
		$oColor = new CSSColor();
		$oColor->fromRGB($aRGB);
		$this->assertEquals($oColor->getColor(), $aExpectedColor);
		$this->assertEquals($oColor->getColorDescription(), $aExpectedDescription);
	}
	public function fromRGBProvider()
	{
		return [
			[
				['r' => 0, 'g' => 255, 'b' => 255],
				[
					'r' => new CSSSize(0, null, true),
					'g' => new CSSSize(255, null, true),
					'b' => new CSSSize(255, null, true)
				],
				'rgb'
			],
			[
				['r' => '100%', 'g' => -5, 'b' => 303],
				[
					'r' => new CSSSize(255, null, true),
					'g' => new CSSSize(0, null, true),
					'b' => new CSSSize(255, null, true),
				],
				'rgb'
			],
			[
				['r' => 0, 'g' => 0, 'b' => 0, 'a' => 2],
				[
					'r' => new CSSSize(0, null, true),
					'g' => new CSSSize(0, null, true),
					'b' => new CSSSize(0, null, true)
				],
				'rgb'
			],
		];
	}

	/**
	 * @dataProvider fromHSLProvider
	 **/
	public function testFromHSL($aHSL, $aExpectedColor, $aExpectedDescription)
	{
		$oColor = new CSSColor();
		$oColor->fromHSL($aHSL);
		$this->assertEquals($oColor->getColor(), $aExpectedColor);
		$this->assertEquals($oColor->getColorDescription(), $aExpectedDescription);
	}
	public function fromHSLProvider()
	{
		return [
			[
				['h' => 60, 's' => '100%', 'l' => '50%'],
				[
					'r' => new CSSSize(255, null, true),
					'g' => new CSSSize(255, null, true),
					'b' => new CSSSize(0, null, true)
				],
				'rgb'
			],
			[
				['h' => 540, 's' => '120%', 'l' => '50%'],
				[
					'r' => new CSSSize(0, null, true),
					'g' => new CSSSize(255, null, true),
					'b' => new CSSSize(255, null, true)
				],
				'rgb'
			],
			[
				['h' => 480, 's' => '120%', 'l' => '-50%', 'a' => 0.3],
				[
					'r' => new CSSSize(0, null, true),
					'g' => new CSSSize(0, null, true),
					'b' => new CSSSize(0, null, true),
					'a' => new CSSSize(0.3, null, true)
				],
				'rgba'
			],
		];
	}

	/**
	 * @dataProvider toHSLProvider
	 **/
	public function testToHSL($oColor, $aExpectedColor)
	{
		$aOriginalColor = $oColor->getColor();
		$oColor->toHSL();
		$this->assertEquals($oColor->getColor(), $aExpectedColor);
		$oColor->toRGB();
		$this->assertEquals(
			$oColor->getColor(),
			$aOriginalColor,
			'Failed to convert color back to RGB'
		);
	}
	public function toHSLProvider()
	{
		return [
			[
				new CSSColor('blue'),
				[
					'h' => new CSSSize(240, null, true),
					's' => new CSSSize(100, '%', true),
					'l' => new CSSSize(50, '%', true)
				]
			],
			[
				new CSSColor(['r' => 255, 'g' => 0, 'b' => 0]),
				[
					'h' => new CSSSize(0, null, true),
					's' => new CSSSize(100, '%', true),
					'l' => new CSSSize(50, '%', true)
				]
			],
			[
				new CSSColor('transparent'),
				[
					'h' => new CSSSize(0, null, true),
					's' => new CSSSize(0, '%', true),
					'l' => new CSSSize(0, '%', true),
					'a' => new CSSSize(0, null, true)
				]
			],
		];
	}
}
