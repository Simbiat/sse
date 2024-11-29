<?php
declare(strict_types = 1);

namespace Simbiat\http;

/**
 * PSimple implementation of Server-Side Events
 */
class SSE
{
    private static bool $counterAsId = false;
    private static int $counter = 0;
    
    /**
     * Open SSE stream
     *
     * @param bool $counterAsId Whether to count
     *
     * @return void
     */
    public static function open(bool $counterAsId = false): void
    {
        self::$counter = 0;
        self::$counterAsId = $counterAsId;
        #Ignore user abort, since this is handled in another place
        ignore_user_abort(true);
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Transfer-Encoding: chunked');
            #Forbid caching, since stream is not supposed to be cached
            header('Cache-Control: no-cache');
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
        @header('Connection: close');
        if ($completely) {
            exit;
        }
    }
    
    /**
     * Send server event to stream
     * @param string      $message Actual message text
     * @param string      $event   Optional event name
     * @param int         $retry   Time in milliseconds after which to reconnect to stream, in case of connection loss
     * @param string|null $id      Event ID. If null will be either a counter (if stream opened with `$counterAsId` set to `true`) or high resolution time of the server
     *
     * @return void
     */
    public static function send(string $message, string $event = '', int $retry = 10000, ?string $id = null): void
    {
        if ($id === null || preg_match('/^\s*$/u', $id) === 1) {
            if (self::$counterAsId) {
                $id = (string)self::$counter++;
            } else {
                $id = (string)hrtime(true);
            }
        } else {
            $id = mb_trim(preg_replace('/[\r\n]/u', '', $message), null, 'UTF-8');
        }
        #Text fields should not have any new lines in them, so strip them
        $event = mb_trim(preg_replace('/[\r\n]/u', '', $event), null, 'UTF-8');
        $message = mb_trim(preg_replace('/[\r\n]/u', '', $message), null, 'UTF-8');
        echo 'retry: '.$retry."\n".'id: '.$id."\n".(empty($event) ? '' : 'event: '.$event."\n").'data: '.$message."\n\n";
        ob_flush();
        flush();
    }
}