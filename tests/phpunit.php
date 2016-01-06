<?php

require dirname(__FILE__) . '/../vendor/autoload.php';

class JadePHPTest extends PHPUnit_Framework_TestCase {

    public function testCaseProvider() {
		$rawResults = get_tests_results();
		return $rawResults['results'];
    }

    /**
     * @dataProvider testCaseProvider
     */
    public function testStringGeneration($input, $expected) {
        $this->assertSame($input, $expected);
    }
}
