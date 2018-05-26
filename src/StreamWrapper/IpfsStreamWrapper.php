<?php

namespace IPFS\StreamWrapper;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Stream;
use IPFS\HttpClientTrait;
use IPFS\StreamContextOptionsTrait;

/**
 * Defines a stream wrapper for IPFS.
 */
class IpfsStreamWrapper
{

    use HttpClientTrait;
    use ImmutableStreamWrapperTrait;
    use StreamContextOptionsTrait;

    /**
     * The URI of the IPFS host.
     *
     * @var string
     *
     * @todo Make this configurable.
     */
    protected $ipfsHost = 'http://127.0.0.1:5001';

    /**
     * The URI of the resource.
     *
     * @var string
     */
    protected $uri;

    /**
     * The response stream.
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    private $stream;

    /**
     * Size of the body that is opened.
     *
     * @var int
     */
    private $size;

    /**
     * Mode in which the stream was opened.
     *
     * @var string
     */
    private $mode;

    /**
     * Iterator used with opendir() related calls.
     *
     * @var \Iterator
     */
    private $objectIterator;

    /**
     * The current path opened by dir_opendir().
     *
     * @var string
     */
    private $openedPath;

    /**
     * Optional configuration for HTTP requests.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Registers the 'ipfs://' stream wrapper
     */
    public static function register()
    {
        $protocol = 'ipfs';
        if (in_array($protocol, stream_get_wrappers())) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, static::class, STREAM_IS_URL);
    }

    /**
     * Sets the absolute stream resource URI.
     *
     * @param string $uri
     *   A string containing the URI that should be used for this instance.
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Returns the stream resource URI.
     *
     * @return string
     *   Returns the current URI of the instance.
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Support for dir_closedir().
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.dir-closedir.php
     */
    public function dir_closedir()
    {
        $this->objectIterator = null;
        gc_collect_cycles();
        return true;
    }

    /**
     * Support for dir_opendir().
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.dir-opendir.php
     */
    public function dir_opendir($path, $options)
    {
        $this->openedPath = $path;
        $path = $this->getTarget($path);
        $this->setUri($this->ipfsHost . '/api/v0/ls?arg=' . $path);
        try {
            $response = $this->request();
            $data = $this->decodeResponse($response);
            $this->objectIterator = new \ArrayIterator($data['Objects'][0]['Links']);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Support for dir_readdir().
     *
     * @return string|false
     *   Returns a string representing the next filename, or FALSE if there is
     *   no next file.
     *
     * @see http://php.net/manual/streamwrapper.dir-readdir.php
     */
    public function dir_readdir()
    {
        // Skip empty result keys.
        if (!$this->objectIterator->valid()) {
            return false;
        }

        $current = $this->objectIterator->current();
        $result = $current['Name'];
        $this->objectIterator->next();

        return $result;
    }

    /**
     * Support for dir_rewinddir().
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.dir-rewinddir.php
     */
    public function dir_rewinddir()
    {
        $this->handleBooleanCall(function () {
            $this->objectIterator = null;
            $this->dir_opendir($this->openedPath, null);
            return true;
        });
    }

    /**
     * Support for stream_cast().
     *
     * @param int $cast_as
     *   Can be STREAM_CAST_FOR_SELECT when stream_select() is calling
     *   stream_cast(), or STREAM_CAST_AS_STREAM when stream_cast() is called
     *   for other uses.
     *
     * @return resource|false
     *   The underlying stream resource or FALSE if stream_select() is not
     *   supported.
     *
     * @see http://php.net/manual/streamwrapper.stream-cast.php
     */
    public function stream_cast($cast_as)
    {
        return false;
    }

    /**
     * Support for stream_close().
     *
     * @see http://php.net/manual/streamwrapper.stream-close.php
     */
    public function stream_close()
    {
        $this->stream = null;
    }

    /**
     * Support for stream_eof().
     *
     * @return bool
     *   Returns TRUE if the read/write position is at the end of the stream
     *   and if no more data is available to be read, or FALSE otherwise.
     *
     * @see http://php.net/manual/streamwrapper.stream-eof.php
     */
    public function stream_eof()
    {
        return $this->stream->eof();
    }

    /**
     * Support for stream_flush() / fflush().
     *
     * @return bool
     *   Returns TRUE if the cached data was successfully stored (or if there
     *   was no data to store), or FALSE if the data could not be stored.
     *
     * @see http://php.net/manual/streamwrapper.stream-flush.php
     */
    public function stream_flush()
    {
        if ($this->mode == 'r') {
            return false;
        }
        if ($this->stream->isSeekable()) {
            $this->stream->seek(0);
        }

        $this->setUri($this->ipfsHost . '/api/v0/add');
        $this->config['multipart'] = [
          [
            'name' => 'file',
            'contents' => $this->stream,
          ],
        ];
        $response = $this->request('POST');

        return $response->getStatusCode() == 200 ? true : false;
    }

    /**
     * Support for stream_lock().
     *
     * @param int $operation
     *   One the following: LOCK_SH, LOCK_EX, LOCK_UN or LOCK_NB.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.stream-lock.php
     */
    public function stream_lock($operation)
    {
        return true;
    }

    /**
     * Support for stream_open().
     *
     * @param string $path
     *   Specifies the URL that was passed to the original function.
     * @param string $mode
     *   The mode used to open the file, as detailed for fopen().
     * @param int $options
     *   Holds additional flags set by the streams API.
     * @param string $opened_path
     *   If the path is opened successfully, and STREAM_USE_PATH is set in
     *   $options, $opened_path should be set to the full path of the
     *   file/resource that was actually opened.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.stream-open.php
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->mode = rtrim($mode, 'bt');
        if ($errors = $this->validate($path, $this->mode)) {
            return $this->triggerError($errors, $options);
        }

        if ($options & STREAM_USE_PATH) {
            $opened_path = $path;
        }

        return $this->handleBooleanCall(function () use ($path) {
            switch ($this->mode) {
                case 'r':
                    return $this->openReadStream($path);
                default:
                    return $this->openWriteStream($path);
            }
        }, $options);
    }

    /**
     * Support for stream_read().
     *
     * @param int $count
     *   How many bytes of data from the current position should be returned.
     *
     * @return string
     *   If there are less than $count bytes available, returns as many as are
     *   available. If no more data is available, returns an empty string.
     *
     * @see http://php.net/manual/streamwrapper.stream-read.php
     */
    public function stream_read($count)
    {
        return $this->stream->read($count);
    }

    /**
     * Support for stream_seek().
     *
     * @param int $offset
     *   The stream offset to seek to.
     * @param int $whence
     *   Possible values:
     *   - SEEK_SET - Set position equal to offset bytes.
     *   - SEEK_CUR - Set position to current location plus offset.
     *   - SEEK_END - Set position to end-of-file plus offset.
     *
     * @return bool
     *   Returns TRUE if the position was updated, FALSE otherwise.
     *
     * @see http://php.net/manual/streamwrapper.stream-seek.php
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return !$this->stream->isSeekable()
          ? false
          : $this->handleBooleanCall(function () use ($offset, $whence) {
              $this->stream->seek($offset, $whence);
              return true;
          });
    }

    /**
     * Support for stream_set_option().
     *
     * This method is called to set options on the stream.
     *
     * @param int $option
     *   One of:
     *   - STREAM_OPTION_BLOCKING: The method was called in response to
     *     stream_set_blocking().
     *   - STREAM_OPTION_READ_TIMEOUT: The method was called in response to
     *     stream_set_timeout().
     *   - STREAM_OPTION_WRITE_BUFFER: The method was called in response to
     *     stream_set_write_buffer().
     * @param int $arg1
     *   If option is:
     *   - STREAM_OPTION_BLOCKING: The requested blocking mode:
     *     - 1 means blocking.
     *     - 0 means not blocking.
     *   - STREAM_OPTION_READ_TIMEOUT: The timeout in seconds.
     *   - STREAM_OPTION_WRITE_BUFFER: The buffer mode, STREAM_BUFFER_NONE or
     *     STREAM_BUFFER_FULL.
     * @param int $arg2
     *   If option is:
     *   - STREAM_OPTION_BLOCKING: This option is not set.
     *   - STREAM_OPTION_READ_TIMEOUT: The timeout in microseconds.
     *   - STREAM_OPTION_WRITE_BUFFER: The requested buffer size.
     *
     * @return bool
     *   TRUE on success, FALSE otherwise. If $option is not implemented, FALSE
     *   should be returned.
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        if ($option != STREAM_OPTION_READ_TIMEOUT) {
            return false;
        }

        $this->config['timeout'] = $arg1 + ($arg2 / 1000000);
        return true;
    }

    /**
     * Support for stream_stat().
     *
     * @return array
     *   An array as returned by stat().
     *
     * @see http://php.net/manual/streamwrapper.stream-stat.php
     * @see http://php.net/manual/function.stat.php
     */
    public function stream_stat()
    {
        $size = $this->stream->getSize() ?: $this->size;
        return $this->generateStat($size, null, $this->mode);
    }

    /**
     * Support for stream_tell().
     *
     * @return int
     *   Returns the current position of the stream.
     *
     * @see http://php.net/manual/streamwrapper.stream-tell.php
     */
    public function stream_tell()
    {
        return $this->handleBooleanCall(function () {
            return $this->stream->tell();
        });
    }

    /**
     * Support for stream_truncate().
     *
     * @param int $new_size
     *   The data that should be stored into the underlying stream.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     *
     * @see http://php.net/manual/streamwrapper.stream-truncate.php
     */
    public function stream_truncate($new_size)
    {
        // @todo Not implemented yet.
        return false;
    }

    /**
     * Support for stream_write().
     *
     * @param string $data
     *   The data that should be stored into the underlying stream.
     *
     * @return int
     *   Return the number of bytes that were successfully stored, or 0 if none
     *   could be stored.
     *
     * @see http://php.net/manual/streamwrapper.stream-write.php
     */
    public function stream_write($data)
    {
        return $this->stream->write($data);
    }

    /**
     * Support for url_stat().
     *
     * @param string $path
     *   The file path or URL to stat.
     * @param int $flags
     *   Holds additional flags set by the streams API. It can hold one or more
     *   of the following values OR'd together:
     *   - STREAM_URL_STAT_LINK
     *   - STREAM_URL_STAT_QUIET
     *
     * @return array
     *   An associative array, as returned by stat().
     *
     * @see http://php.net/manual/streamwrapper.url-stat.php
     */
    public function url_stat($path, $flags)
    {
        $size = $type = null;
        try {
            $path = $this->getTarget($path);
            $this->setUri($this->ipfsHost . '/api/v0/cat?arg=' . $path);
            $response = $this->requestTryHeadLookingForHeader($this->uri, 'X-Content-Length');

            if ($response->hasHeader('X-Content-Length')) {
                $size = (int)$response->getHeaderLine('X-Content-Length');
            } elseif ($body_size = $response->getBody()->getSize()) {
                $size = $body_size;
            }
            $type = '2';
        } catch (ServerException $exception) {
            $response_body = $this->decodeResponse($exception->getResponse());
            if (isset($response_body['Message']) && $response_body['Message'] === 'this dag node is a directory') {
                $size = 0;
                $type = '1';
            }
        } catch (\Exception $exception) {
            $this->triggerError($exception->getMessage(), $flags);
        }

        if ($size !== null && $type !== null) {
            $stat = $this->generateStat($size, $type);

            return $stat;
        }

        return null;
    }

    /**
     * Perform an HTTP request for the current URI.
     *
     * @param string $method
     *   The HTTP method.
     *
     * @return \Psr\Http\Message\ResponseInterface
     *   The HTTP response object.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method = 'GET')
    {
        $response = $this->getHttpClient()->request($method, $this->uri, $this->config);
        if ($method !== 'HEAD') {
            $this->stream = $response->getBody();
        }
        return $response;
    }

    /**
     * Returns the current HTTP client configuration.
     *
     * @return array
     */
    public function getHttpConfig()
    {
        return $this->config;
    }

    /**
     * Returns the local writable target of the resource within the stream.
     *
     * This function should be used in place of calls to realpath() or similar
     * functions when attempting to determine the location of a file. While
     * functions like realpath() may return the location of a read-only file,
     * this method may return a URI or path suitable for writing that is
     * completely separate from the URI used for reading.
     *
     * @param string $uri
     *   Optional URI.
     *
     * @return string|bool
     *   Returns a string representing a location suitable for writing of a
     *   file, or FALSE if unable to write to the file such as with read-only
     *   streams.
     */
    private function getTarget($uri = null)
    {
        if (!isset($uri)) {
            $uri = $this->uri;
        }

        list(, $target) = explode('://', $uri, 2);

        // Remove erroneous leading or trailing, forward-slashes and backslashes.
        return trim($target, '\/');
    }

    /**
     * Invokes a callable and triggers an error if an exception occurs while
     * calling the function.
     *
     * @param callable $function
     *   The function to call.
     * @param int $flags
     *   Holds additional flags set by the streams API.
     *
     * @return bool
     *   TRUE on success, FALSE otherwise.
     */
    private function handleBooleanCall(callable $function, $flags = null)
    {
        try {
            return $function();
        } catch (\Exception $e) {
            return $this->triggerError($e->getMessage(), $flags);
        }
    }

    /**
     * Trigger one or more errors.
     *
     * @param string|array $errors
     *   Errors to trigger
     * @param int $flags
     *    If set to STREAM_URL_STAT_QUIET, then no error or exception occurs.
     *
     * @return bool|array
     *   Returns FALSE or an empty stat template when the STREAM_URL_STAT_LINK
     *   flag is set.
     *
     * @throws \RuntimeException
     *   If throw_errors is TRUE.
     */
    private function triggerError($errors, $flags = null)
    {
        // This is triggered with things like file_exists() or fopen().
        if ($flags & STREAM_URL_STAT_QUIET || $flags & STREAM_REPORT_ERRORS) {
            return $flags & STREAM_URL_STAT_LINK
                // This is triggered for things like is_link().
              ? $this->generateStat(0, false)
              : false;
        }

        // This is triggered when doing things like lstat() or stat().
        trigger_error(implode("\n", (array)$errors), E_USER_WARNING);

        return false;
    }

    /**
     * Generates an array as returned by the stat() function.
     *
     * @param string $size
     *   The size of the resource, in bytes.
     * @param string $type
     *   The type of the resource, '1' for directories and '2' for files.
     * @param int $mode
     *   (optional) The mode in which the stream was opened. Defaults to NULL,
     *   which means that the mode will be derived from $type.
     *
     * @return array
     *   An associative array, as returned by stat().
     *
     * @see http://php.net/manual/en/function.stat.php
     */
    private function generateStat($size, $type, $mode = null)
    {
        // @see https://github.com/guzzle/psr7/blob/master/src/StreamWrapper.php
        $stat = [
          'dev' => 0,
          'ino' => 0,
          'mode' => 0,
          'nlink' => 0,
          'uid' => 0,
          'gid' => 0,
          'rdev' => 0,
          'size' => 0,
          'atime' => 0,
          'mtime' => 0,
          'ctime' => 0,
          'blksize' => -1,
          'blocks' => -1,
        ];

        // 0100000 - bit mask for a regular file;
        // 0040000 - bit mask for a directory.
        // 0444 - bit mask to specify that the resource is read-only, since IPFS
        // resources are immutable.
        // @see "man 2 stat"
        if ($mode) {
            $stat['mode'] = $mode;
        } elseif ($type === '1') {
            $stat['mode'] = 0040000 | 0444;
        } elseif ($type === '2') {
            $stat['mode'] = 0100000 | 0444;
        }
        $stat['size'] = $size;

        return $stat;
    }

    /**
     * Validates the provided stream arguments for fopen() and returns an array
     * of errors.
     *
     * @param string $path
     *   Specifies the URL that was passed to the original function.
     * @param string $mode
     *   The mode used to open the file, as detailed for fopen(), but without
     *   any trailing 'b' or 't' suffix.
     *
     * @return array
     *   An array of errors, if there are any.
     */
    private function validate($path, $mode)
    {
        $errors = [];
        $stat = $this->url_stat($path, null);

        if (!$stat && $mode === 'r') {
            $errors[] = "The file '{$path}' does not exist.";
        }

        if ($stat && $stat['mode'] & 0040000) {
            $errors[] = 'Can not open a directory.';
        }

        if (!in_array($mode, ['r', 'w', 'x'], true)) {
            $errors[] = "Mode not supported: {$mode}. Use 'r', 'w' or 'x'.";
        }

        return $errors;
    }

    /**
     * Opens a read stream for the given path.
     *
     * @param string $uri
     *   An IPFS stream URI.
     *
     * @return bool
     *   Returns TRUE on success.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function openReadStream($uri)
    {
        $path = $this->getTarget($uri);
        $this->setUri($this->ipfsHost . '/api/v0/cat?arg=' . $path);
        $this->request();

        $this->size = $this->stream->getSize();

        // Wrap the body in a caching entity body if seeking is allowed.
        if ($this->getOption('seekable') && !$this->stream->isSeekable()) {
            $this->stream = new CachingStream($this->stream);
        }
        return true;
    }

    /**
     * Opens a write stream for the given path.
     *
     * @param string $uri
     *   An IPFS stream URI.
     *
     * @return bool
     *   Returns TRUE on success.
     */
    private function openWriteStream($uri)
    {
        $this->stream = new Stream(fopen('php://temp', 'r+'));
        return true;
    }
}
