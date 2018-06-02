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
                $files[] = (string) $file;
            } elseif ($type === 'dir') {
                $dirs[] = (string) $file;
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
}
