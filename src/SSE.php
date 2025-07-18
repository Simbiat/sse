<?php
declare(strict_types = 1);

namespace Simbiat\HTTP;

/**
 * Simple implementation of Server-Sent Events
 */
class SSE
{
    /**
     * @var bool Flag indicating if IDs are generated by counting events
     */
    private static bool $counter_as_id = false;
    /**
     * @var int Current event ID
     */
    private static int $counter = 1;
    /**
     * @var bool Flag indicating if SSE mode is possible or not
     */
    public static bool $sse_possible = false;
    /**
     * @var bool Flag indicating if SSE mode is enabled or not
     */
    public static bool $sse = false;
    
    /**
     * Check if SSE mode is possible
     * @param bool $throw Whether to throw an exception if it's not possible
     *
     * @return bool
     */
    public static function isPossible(bool $throw = false): bool
    {
        if (\headers_sent()) {
            if ($throw) {
                throw new \RuntimeException('Headers already sent, can\'t enable SSE mode');
            }
            return false;
        }
        if (!self::$sse_possible && !self::$sse) {
            if (\preg_match('/^cli(-server)?$/i', \PHP_SAPI) === 1) {
                #SSE is not possible in CLI mode
                if ($throw) {
                    throw new \RuntimeException('SSE is not possible in CLI mode');
                }
                return false;
            }
            foreach (\headers_list() as $header) {
                #Check if the header starts with 'Content-Type'
                if (0 === \strncasecmp($header, 'Content-Type', 12)) {
                    #Check if it is an event stream
                    if (\preg_match('/^Content-Type:\s*text\/event-stream/iu', $header) === 1) {
                        self::$sse_possible = true;
                        return self::$sse_possible;
                    }
                    if ($throw) {
                        throw new \RuntimeException('`Content-Type` header has already been sent and is not `event-stream`');
                    }
                    return false;
                }
            }
            self::$sse_possible = true;
        }
        return self::$sse_possible;
    }
    
    /**
     * Open SSE stream
     *
     * @param bool $counter_as_id Whether to count IDs
     *
     * @return void
     */
    public static function open(bool $counter_as_id = false): void
    {
        self::$counter = 0;
        self::$counter_as_id = $counter_as_id;
        #Ignore user abort, since this is handled in another place
        \ignore_user_abort(true);
        self::isPossible(true);
        if (!self::$sse) {
            \header('Content-Type: text/event-stream');
            \header('Transfer-Encoding: chunked');
            #Forbid caching, since the stream is not supposed to be cached
            \header('Cache-Control: no-cache');
            self::$sse = true;
        }
    }
    
    /**
     * Open SSE stream
     *
     * @param bool $completely Exit the script completely, if `true`
     *
     * @return void
     */
    public static function close(bool $completely = false): void
    {
        # Suppress the silence operator inspection. While normally we can use `headers_sent` to check if headers were sent, with a stream it does not really make sense
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @\header('Connection: close');
        if ($completely) {
            exit(0);
        }
    }
    
    /**
     * Send server event to stream
     * @param string      $message Actual message text
     * @param string      $event   Optional event name
     * @param int         $retry   Time in milliseconds after which to reconnect to stream, in case of connection loss
     * @param string|null $id      Event ID. If null will be either a counter (if the stream opened with `$counter_as_id` set to `true`) or high resolution time of the server
     *
     * @return void
     */
    public static function send(string $message, string $event = '', int $retry = 10000, ?string $id = null): void
    {
        if ($id === null || \preg_match('/^\s*$/u', $id) === 1) {
            if (self::$counter_as_id) {
                $id = (string)self::$counter++;
            } else {
                $id = (string)\hrtime(true);
            }
        } else {
            $id = mb_trim(\preg_replace('/[\r\n]/u', '', $message), null, 'UTF-8');
        }
        #Text fields should not have any new lines in them, so strip them
        $event = mb_trim(\preg_replace('/[\r\n]/u', '', $event), null, 'UTF-8');
        $message = mb_trim(\preg_replace('/[\r\n]/u', '', $message), null, 'UTF-8');
        echo 'retry: '.$retry."\n".'id: '.$id."\n".(empty($event) ? '' : 'event: '.$event."\n").'data: '.$message."\n\n";
        \ob_flush();
        \flush();
    }
}