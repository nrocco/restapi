<?php

namespace RestApi;

class HashedStorage
{
    protected $basepath;

    public function __construct($path)
    {
        $this->setBasePath($path);
    }

    public function setBasePath($path)
    {
        $this->basepath = $path;
    }

    public function getBasePath()
    {
        return $this->basepath;
    }

    public function save($file)
    {
        if (false === file_exists($file)) {
            throw new \RuntimeException("The source file {$file} does not exist");
        }

        $hash = $this->generateHash($file);

        if (true === $this->exists($hash)) {
            return $hash;
        }

        $directory = $this->basepath.'/'.$this->hashToDirectory($hash);
        $filePath = $this->hashToFullFilePath($hash);

        if (false === is_dir($directory)) {
            if (true !== mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Could not create parent directory {$directory}");
            }
        }

        if (true !== rename($file, $filePath)) {
            throw new \RuntimeException("Could not save file as {$filePath}");
        }

        return $hash;
    }

    public function exists($hash)
    {
        return file_exists($this->hashToFullFilePath($hash));
    }

    public function hashToFullFilePath($hash)
    {
        $path = $this->hashToFilePath($hash);

        return "{$this->basepath}/{$path}";
    }

    protected function generateHash($file)
    {
        return md5_file($file);
    }

    protected function hashToFilePath($hash)
    {
        return $this->hashToDirectory($hash).'/'.$hash;
    }

    protected function hashToDirectory($hash)
    {
        return substr($hash, 0, 1) . '/'.substr($hash, 1, 1);
    }
}
