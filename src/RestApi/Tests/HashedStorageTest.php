<?php

namespace RestApi\Tests;

use RestApi\HashedStorage;

class HashedStorageTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->tempdir = sys_get_temp_dir();
        $this->storagedir = "{$this->tempdir}/__w00t__";

        if (false === file_exists($this->storagedir)) {
            mkdir($this->storagedir);

            return;
        }

        exec("rm -rf {$this->storagedir}/*");
    }

    protected function getTemporaryFile($content = null)
    {
        $tempFile = tempnam($this->tempdir, 'w00t');

        if (null !== $content) {
            file_put_contents($tempFile, $content);
        }

        return $tempFile;
    }

    public function testClass()
    {
        $storage = new HashedStorage($this->storagedir);

        $this->assertEquals($this->storagedir, $storage->getBasePath());
    }

    public function testSaveFile()
    {
        $storage = new HashedStorage($this->storagedir);
        $tempFile = $this->getTemporaryFile('Hello World');
        $hash = $storage->save($tempFile);

        $this->assertEquals('b10a8db164e0754105b7a99be72e3fe5', $hash);
        $this->assertTrue($storage->exists('b10a8db164e0754105b7a99be72e3fe5'));
        $this->assertEquals("{$this->storagedir}/b/1/b10a8db164e0754105b7a99be72e3fe5", $storage->hashToFullFilePath($hash));

        $tempFile = $this->getTemporaryFile('Hello World');
        $hash2 = $storage->save($tempFile);

        $this->assertEquals($hash, $hash2);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSaveUnexistingFile()
    {
        $storage = new HashedStorage($this->storagedir);
        $storage->save(__DIR__.'/oohja.txt');
    }
}
