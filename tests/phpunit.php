<?php

require dirname(__FILE__) . '/../vendor/autoload.php';

class JadePHPTest extends PHPUnit_Framework_TestCase {

    public function testCaseProvider() {
<<<<<<< HEAD
        static $rawResults = null;
        if(is_null($rawResults)) {
            $rawResults = get_tests_results();
            $rawResults = $rawResults['results'];
        }
        return $rawResults;
=======
		chdir(dirname(__FILE__));
		$rawResults = get_tests_results();
		return $rawResults['results'];
>>>>>>> 84ed821... Allow using PHPUnit to run tests
    }

    /**
     * @dataProvider testCaseProvider
     */
    public function testStringGeneration($input, $expected) {
        $this->assertSame($input, $expected);
    }
}
