<?php

use Pug\Pug;

class PugTest extends Pug
{
    protected $compilationsCount = 0;

    public function getCompilationsCount()
    {
        return $this->compilationsCount;
    }

    public function compileFile($input)
    {
        $this->compilationsCount++;

        return parent::compileFile($input);
    }
}

class PugCacheTest extends PHPUnit_Framework_TestCase
{
    protected function emptyDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->emptyDirectory($path);
                } else {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 5
     */
    public function testMissingDirectory()
    {
        $pug = new Pug(array(
            'singleQuote' => false,
            'cache' => '///cannot/be/created'
        ));
        $pug->render(__DIR__ . '/../templates/attrs.pug');
    }

    /**
     * Cache from string input
     */
    public function testFileCache()
    {
        $dir = sys_get_temp_dir() . '/pug';
        if (file_exists($dir)) {
            if (is_file($dir)) {
                unlink($dir);
                mkdir($dir);
            } else {
                $this->emptyDirectory($dir);
            }
        } else {
            mkdir($dir);
        }
        $test = "$dir/test.pug";
        file_put_contents($test, "header\n  h1#foo Hello World!\nfooter");
        $pug = new PugTest(array(
            'debug' => false,
            'cache' => $dir
        ));
        $this->assertSame(0, $pug->getCompilationsCount(), 'Should have done no compilations yet');
        $pug->renderFile($test);
        $this->assertSame(1, $pug->getCompilationsCount(), 'Should have done 1 compilation');
        $pug->renderFile($test);
        $this->assertSame(1, $pug->getCompilationsCount(), 'Should have done always 1 compilation because the code is cached');
        file_put_contents($test, "header\n  h1#foo Hello World2\nfooter");
        $pug->renderFile($test);
        $this->assertSame(2, $pug->getCompilationsCount(), 'Should have done always 2 compilations because the code changed');
        $this->emptyDirectory($dir);
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionCode 6
     */
    public function testReadOnlyDirectory()
    {
        $dir = __DIR__;
        while (is_writeable($dir)) {
            $parent = realpath($dir . '/..');
            if ($parent === $dir) {
                $dir = 'C:';
                if (!file_exists($dir) || is_writable($dir)) {
                    throw new \ErrorException('No read-only directory found to do the test', 6);
                }
                break;
            }
            $dir = $parent;
        }
        $pug = new Pug(array(
            'singleQuote' => false,
            'cache' => $dir,
        ));
        $pug->cache(__DIR__ . '/../templates/attrs.pug');
    }

    private function cacheSystem($keepBaseName)
    {
        $cacheDirectory = sys_get_temp_dir() . '/pug-test';
        $this->emptyDirectory($cacheDirectory);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }
        $file = tempnam(sys_get_temp_dir(), 'Pug-test-');
        $pug = new Pug(array(
            'singleQuote' => false,
            'keepBaseName' => $keepBaseName,
            'cache' => $cacheDirectory,
        ));
        copy(__DIR__ . '/../templates/attrs.pug', $file);
        $name = basename($file);
        $stream = $pug->cache($file);
        $phpFiles = array_values(array_map(function ($file) use ($cacheDirectory) {
            return $cacheDirectory . DIRECTORY_SEPARATOR . $file;
        }, array_filter(scandir($cacheDirectory), function ($file) {
            return substr($file, -4) === '.php';
        })));
        $start = 'Pug.stream://data;';
        $this->assertTrue(strpos($stream, $start) === 0, 'Fresh content should be a stream.');
        $this->assertSame(1, count($phpFiles), 'The cached file should now exist.');
        $cachedFile = realpath($phpFiles[0]);
        $this->assertFalse(!$cachedFile, 'The cached file should now exist.');
        $this->assertSame($stream, $pug->stream($pug->compile($file)), 'Should return the stream of attrs.pug.');
        $this->assertStringEqualsFile($cachedFile, substr($stream, strlen($start)), 'The cached file should contains the same contents.');
        touch($file, time() - 3600);
        $path = $pug->cache($file);
        $this->assertSame(realpath($path), $cachedFile, 'The cached file should be used instead if untouched.');
        copy(__DIR__ . '/../templates/mixins.pug', $file);
        touch($file, time() + 3600);
        $stream = $pug->cache($file);
        $this->assertSame($stream, $pug->stream($pug->compile(__DIR__ . '/../templates/mixins.pug')), 'The cached file should be the stream of mixins.pug.');
        unlink($file);
    }

    /**
     * Normal function
     */
    public function testCache()
    {
        $this->cacheSystem(false);
    }

    /**
     * Test option keepBaseName
     */
    public function testCacheWithKeepBaseName()
    {
        $this->cacheSystem(true);
    }

    /**
     * Test cacheDirectory method
     */
    public function testCacheDirectory()
    {
        $cacheDirectory = sys_get_temp_dir() . '/pug-test';
        $this->emptyDirectory($cacheDirectory);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }
        $templatesDirectory = __DIR__ . '/../templates';
        $pug = new Pug(array(
            'basedir' => $templatesDirectory,
            'cache' => $cacheDirectory,
        ));
        list($success, $errors) = $pug->cacheDirectory($templatesDirectory);
        $filesCount = count(array_filter(scandir($cacheDirectory), function ($file) {
            return $file !== '.' && $file !== '..';
        }));
        $expectedCount = count(array_filter(array_merge(
            scandir($templatesDirectory),
            scandir($templatesDirectory . '/auxiliary'),
            scandir($templatesDirectory . '/auxiliary/subdirectory/subsubdirectory')
        ), function ($file) {
            return in_array(pathinfo($file, PATHINFO_EXTENSION), array('pug', 'Pug'));
        }));
        $this->emptyDirectory($cacheDirectory);
        $templatesDirectory = __DIR__ . '/../templates/subdirectory/subsubdirectory';
        $pug = new Pug(array(
            'basedir' => $templatesDirectory,
            'cache' => $cacheDirectory,
        ));
        $this->emptyDirectory($cacheDirectory);
        rmdir($cacheDirectory);

        $this->assertSame($expectedCount, $success + $errors, 'Each .pug file in the directory to cache should generate a success or an error.');
        $this->assertSame($success, $filesCount, 'Each file successfully cached should be in the cache directory.');
    }
}
