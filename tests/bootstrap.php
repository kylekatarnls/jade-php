<?php

function setup_autoload() {
    // quick setup for autoloading
    $path = str_replace('/', DIRECTORY_SEPARATOR, dirname(__FILE__) . '/../');
    $path = realpath($path);
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);

    spl_autoload_register(function ($class) {
        $file = dirname(__FILE__) . '/../src/' . str_replace("\\", DIRECTORY_SEPARATOR, $class) . '.php';
        if(file_exists($file)) {
            require_once($file);
        }
    });
}

function find_tests() {
    // find the tests
    $path = str_replace('/', DIRECTORY_SEPARATOR, dirname(__FILE__) . '/');
    $path = realpath($path);
    return glob($path . DIRECTORY_SEPARATOR . '*.jade');
}

function build_list($test_list) {
    $group_list = array();
    foreach ($test_list as $test) {
        $name = basename($test, '.jade');
        $parts = preg_split('/[.-]/', $name);

        if (!isset($group_list[$parts[0]])) {
            $group_list[$parts[0]] = array();
        }
        $group_list[$parts[0]][] = array('link' => $test, 'name' => $name);
    }

    return $group_list;
}

function show_php($file) {
    $jade = new \Jade\Jade();
    return $jade->render($file);
}

function init_tests() {
    mb_internal_encoding('utf-8');
    error_reporting(E_ALL);
    setup_autoload();
}

function get_generated_html($contents) {
    if(ini_get('allow_url_include') | 0) {
        error_reporting(E_ALL & ~E_NOTICE);
        ob_start();
        include "data://text/plain;base64," . base64_encode($contents);
        $contents = ob_get_contents();
        ob_end_clean();
        error_reporting(E_ALL);
    } else {
        $file = tempnam(sys_get_temp_dir(), 'jade');
        file_put_contents($file, $contents);
        $contents = `php -d error_reporting="E_ALL & ~E_NOTICE" {$file}`;
        unlink($file);
    }
    return $contents;
}

function get_tests_results($verbose = false) {

    global $argv;

    $initialDirectory = getcwd();
    chdir(dirname(__FILE__));

    init_tests();

    $nav_list = build_list(find_tests());

    $success = 0;
    $failures = 0;
    $results = array();

    foreach($nav_list as $type => $arr) {
        foreach($arr as $e) {
            if($e['name'] == 'index' || (isset($argv[1]) && $e['name'] != $argv[1] && $argv[1] != '.')) {
                continue;
            }

            $html = @file_get_contents($e['name'] . '.html');
            if($html === FALSE) {
                if($verbose) {
                    echo "! sample for test '$e[name]' not found.\n";
                }
                continue;
            }

            if($verbose) {
                echo "* rendering test '$e[name]'\n";
            }
            try {
                $new = show_php($e['name'] . '.jade');
            } catch(Exception $err) {
                throw $err;
                if($verbose) {
                    echo "! FATAL: php exception: ".str_replace("\n", "\n\t", $err)."\n";
                }
                $new = null;
            }

            if($new !== null) {
                
                $code = get_generated_html($new);

                // automatically compare $code and $html here
                $from = array("\n", "\r", "\t", " ", '"', "<!DOCTYPEhtml>");
                $to = array('', '', '', '', "'", '');
                $html = str_replace($from, $to, $html);
                $code = str_replace($from, $to, $code);
                $results[] = array($html, $code);
                if(strcmp($html, $code)) {
                    $failures++;
                    if($verbose) {
                        echo "  -$html\n";
                        echo "  +$code\n\n";
                    }
                    if(isset($argv[1]) && $argv[1] == '.') // render until first difference
                        die;
                } else {
                    $success++;
                }
            }
        }
    }

    chdir($initialDirectory);

    return array(
        'success' => $success,
        'failures' => $failures,
        'results' => $results
    );
}
