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
     * @covers ::register
     */
    public function testRegister()
    {
        IpfsStreamWrapper::register();

        $this->assertContains('ipfs', \stream_get_wrappers());
    }
}
