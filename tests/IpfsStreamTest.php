<?php

namespace IPFS\Tests;

use IPFS\StreamWrapper\IpfsStreamWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \IPFS\StreamWrapper\IpfsStreamWrapper
 *
 * @todo Add more test coverage :)
 */
class IpfsStreamTest extends TestCase
{

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        IpfsStreamWrapper::register();
    }

    /**
     * @covers ::register
     */
    public function testRegister()
    {
        $this->assertContains('ipfs', \stream_get_wrappers());
    }

    /**
     * @covers ::dir_closedir()
     * @covers ::dir_opendir()
     * @covers ::dir_readdir()
     * @covers ::dir_rewinddir()
     */
    public function testDirectoryTraversal()
    {
        // go-ipfs initializes a directory structure by default with this hash:
        // QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG and the following
        // contents:
        // - about
        // - contact
        // - help
        // - quick-start
        // - readme
        // - security-notes
        $dir_uri = 'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';

        // Open a known directory, and proceed to read its contents.
        $files = $dirs = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir_uri));
        foreach ($iterator as $file) {
            $type = $file->getType();

            if ($type === 'file') {
                $files[] = (string)$file;
            } elseif ($type === 'dir') {
                $dirs[] = (string)$file;
            }
        }

        $expected = [
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/about',
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/contact',
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/help',
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/quick-start',
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/readme',
            'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/security-notes',
        ];
        $this->assertSame($expected, $files);
        $this->assertSame([], $dirs);
    }

    /**
     * @covers ::url_stat
     * @covers ::getStatTemplate
     *
     * @dataProvider statCasesProvider
     */
    public function testStat($uri, $exists, $is_dir, $is_file, $size)
    {
        $this->assertSame($exists, file_exists($uri));
        $this->assertSame($is_dir, is_dir($uri));
        $this->assertSame($is_file, is_file($uri));
        if ($exists) {
            $this->assertSame(stat($uri)['size'], $size);
        }
    }

    public function statCasesProvider()
    {
        return [
            'empty dir' => [
                'uri' => 'ipfs://QmUNLLsPACCz1vLxQVkXqqLX5R1X345qqfHbsf67hvA3Nn',
                'exists' => true,
                'is_dir' => true,
                'is_file' => false,
                'size' => 0,
            ],
            'non-empty dir' => [
                'uri' => 'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG',
                'exists' => true,
                'is_dir' => true,
                'is_file' => false,
                'size' => 0,
            ],
            'non-existent dir' => [
                'uri' => 'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG2',
                'exists' => false,
                'is_dir' => false,
                'is_file' => false,
                'size' => 0,
            ],
            'file' => [
                'uri' => 'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/about',
                'exists' => true,
                'is_dir' => false,
                'is_file' => true,
                'size' => 1677,
            ],
            'non-existent file' => [
                'uri' => 'ipfs://QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG/about123',
                'exists' => false,
                'is_dir' => false,
                'is_file' => false,
                'size' => 0,
            ],
        ];
    }
}
