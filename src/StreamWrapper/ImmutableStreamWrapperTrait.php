<?php

namespace IPFS\StreamWrapper;

/**
 * Trait that contains all unimplemented methods.
 *
 * This trait should only contain methods defined in
 * http://php.net/manual/en/class.streamwrapper.php
 *
 * @codeCoverageIgnore
 *   Since these are all FALSE returns, we don't bother to unit test them.
 *
 * @codingStandardsIgnoreStart
 */
trait ImmutableStreamWrapperTrait
{

    /**
     * Support for rename().
     *
     * The file or directory will not be renamed from the stream as IPFS
     * resources are immutable.
     *
     * @param string $path_from
     *   The URL to the current file.
     * @param string $path_to
     *   The URL which the $path_from should be renamed to.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.rename.php
     */
    public function rename($path_from, $path_to)
    {
        return $this->triggerError('rename() is not supported for IPFS resources.');
    }

    /**
     * Support for rmdir().
     *
     * The directory will not be removed from the stream as IPFS resources are
     * immutable.
     *
     * @param string $path
     *   The directory URL which should be removed.
     * @param int $options
     *   A bit mask of values, such as STREAM_MKDIR_RECURSIVE.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.rmdir.php
     */
    public function rmdir($path, $options)
    {
        return $this->triggerError('rmdir() is not supported for IPFS resources.');
    }

    /**
     * Support for stream_metadata().
     *
     * Creating an empty directory in IPFS does not make sense because the
     * resulting hash can not used subsequently, for example when trying to add
     * files to it.
     *
     * @param string $path
     *   The file path or URL to set metadata. Note that in the case of a URL,
     *   it must be a :// delimited URL. Other URL forms are not supported.
     * @param int $option
     *   One of: STREAM_META_TOUCH, STREAM_META_OWNER_NAME, STREAM_META_OWNER,
     *   STREAM_META_GROUP_NAME, STREAM_META_GROUP or STREAM_META_ACCESS.
     * @param mixed $value
     *   Depends on $option.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure. If $option is not
     *   implemented, FALSE should be returned.
     *
     * @see http://php.net/manual/streamwrapper.stream-metadata.php
     */
    public function stream_metadata($path, $option, $value)
    {
        return $this->triggerError('stream_metadata() is not (yet) supported by IPFS.');
    }

    /**
     * Support for unlink().
     *
     * The file will not be deleted from the stream as IPFS resources are
     * immutable.
     *
     * @param string $path
     *   A string containing the URI to the resource to delete.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.unlink.php
     */
    public function unlink($path)
    {
        return $this->triggerError('unlink() is not supported for IPFS resources.');
    }

}
