<?php

namespace Spatie\HttpLogger;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DefaultLogWriter implements LogWriter
{
    protected $sanitizer;

    public function logRequest(Request $request, Response $response, int $duration)
    {
        $message = $this->formatMessage($this->getMessage($request, $response, $duration));

        Log::channel(config('http-logger.log_channel'))->log(config('http-logger.log_level', 'info'), $message);
    }

    public function getMessage(Request $request, Response $response, int $duration)
    {
        $files = (new Collection(iterator_to_array($request->files)))
            ->map([$this, 'flatFiles'])
            ->flatten();

        $userId = Auth::id() ?? 'guest';

        return [
            'method' => strtoupper($request->getMethod()),
            'uri' => $request->getPathInfo(),
            'body' => $request->except(config('http-logger.except')),
            'headers' => $this->getSanitizer()->clean($request->headers->all(), config('http-logger.sanitize_headers')),
            'files' => $files,
            'user_id' => $userId,
            'response_code' => $response->getStatusCode(),
            'error_text' => $response->exception?->getMessage(),
            'duration_ms' => $duration,
        ];
    }

    protected function formatMessage(array $message)
    {
        $bodyAsJson = json_encode($message['body']);
        $headersAsJson = json_encode($message['headers']);
        $files = $message['files']->implode(',');

        return "{$message['method']} {$message['uri']} - Response Code: {$message['response_code']} - Error Text: {$message['error_text']} - Duration: {$message['duration_ms']} - User: {$message['user_id']} - Headers: {$headersAsJson} - Body: {$bodyAsJson} - Files: " . $files;
    }

    public function flatFiles($file)
    {
        if ($file instanceof UploadedFile) {
            return $file->getClientOriginalName();
        }
        if (is_array($file)) {
            return array_map([$this, 'flatFiles'], $file);
        }

        return (string) $file;
    }

    protected function getSanitizer()
    {
        if (! $this->sanitizer instanceof Sanitizer) {
            $this->sanitizer = new Sanitizer();
        }

        return $this->sanitizer;
    }
}
