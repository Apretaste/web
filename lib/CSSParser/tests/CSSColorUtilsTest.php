<?php
require_once __DIR__.'/../lib/CSSColorUtils.php';

class CSSColorUtilsTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider normalizeFractionProvider
	 **/
	public function testNormalizeFraction($mValue, $fExcpected)
	{
		$fResult = CSSColorUtils::normalizeFraction($mValue);
		$this->assertEquals($fResult, $fExcpected);
	}
	public function normalizeFractionProvider()
	{
		return [
	  [150,     1],
	  ['150%',  1],
	  ['50%',   0.5],
	  [50,      0.5],
	  [-150,    0],
	  ['-150%', 0],
	];
	}

	/**
	 * @dataProvider normalizeRGBValueProvider
	 **/
	public function testNormalizeRGBValue($mValue, $fExcpected)
	{
		$fResult = CSSColorUtils::normalizeRGBValue($mValue);
		$this->assertEquals($fResult, $fExcpected);
	}
	public function normalizeRGBValueProvider()
	{
		return [
	  [150,     150],
	  ['150%',  255],
	  ['50%',   128],
	  [-150,    0],
	  ['-150%', 0],
	];
	}

	/**
	 * @dataProvider hex2rgbProvider
	 **/
	public function testHex2rgb($sHexValue, $aExpected)
	{
		$aRGB = CSSColorUtils::hex2rgb($sHexValue);
		$this->assertSame($aRGB, $aExpected);
	}
	public function hex2rgbProvider()
	{
		return [
	  ['#ff0000', ['r' => 255, 'g' => 0,   'b' => 0]],
	  ['00ff00',  ['r' => 0,   'g' => 255, 'b' => 0]],
	  ['#00f',    ['r' => 0,   'g' => 0,   'b' => 255]],
	  ['BADA55',  ['r' => 186, 'g' => 218, 'b' => 85]],
	  ['#FAIL',    false],
	  // TODO: how do we handle that ?
	  ['FOOBAR',  ['r' => 0, 'g' => 15, 'b' => 186]],
	];
	}

	/**
	 * @dataProvider rgb2hexProvider
	 * @depends testNormalizeRGBValue
	 **/
	public function testRgb2hex($r, $g, $b, $sExpected)
	{
		$sHexValue = CSSColorUtils::rgb2hex($r, $g, $b);
		$this->assertSame($sHexValue, $sExpected);
	}
	public function rgb2hexProvider()
	{
		return [
	  [255, 0,   0,      '#ff0000'],
	  [0,   0,   255,    '#0000ff'],
	  [186, 218, 85,     '#bada55'],
	  [302, -3,  'fail', '#ff0000'],
	];
	}

	/**
	 * @dataProvider hsl2rgbProvider
	 * @depends testNormalizeFraction
	 **/
	public function testHsl2rgb($h, $s, $l, $aExpected)
	{
		$aRGB = CSSColorUtils::hsl2rgb($h, $s, $l, $aExpected);
		// assertEquals because hsl2rgb returns an array of floats,
		// even if they are rounded
		$this->assertEquals($aRGB, $aExpected);
	}
	public function hsl2rgbProvider()
	{
		return [
	  [60,  '100%', '50%',  ['r' => 255, 'g' => 255, 'b' => 0]],
	  [60,  '100%', '25%',  ['r' => 128, 'g' => 128, 'b' => 0]],
	  [480, '120%', '-50%', ['r' => 0,   'g' => 0,   'b' => 0]],
	  [540, '120%', '50%',  ['r' => 0,   'g' => 255, 'b' => 255]],
	];
	}

	/**
	 * @dataProvider rgb2hslProvider
	 * @depends testNormalizeRGBValue
	 **/
	public function testRgb2hsl($r, $g, $b, $aExpected)
	{
		$aHSL = CSSColorUtils::rgb2hsl($r, $g, $b, $aExpected);
		// assertEquals because hsl2rgb returns an array of floats,
		// even if they are rounded
		$this->assertEquals($aHSL, $aExpected);
	}
	public function rgb2hslProvider()
	{
		return [
	  [0,      0,      255, ['h' => 240, 's' => '100%', 'l' => '50%']],
	  [0,      255,    255, ['h' => 180, 's' => '100%', 'l' => '50%']],
	  ['100%', 255,    0,   ['h' => 60,  's' => '100%', 'l' => '50%']],
	  [382,    '150%', -50, ['h' => 60,  's' => '100%', 'l' => '50%']],
	];
	}
}
