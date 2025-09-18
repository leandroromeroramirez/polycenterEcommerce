<?php

namespace Polycenter\MessagingShipping\Exceptions;

use Exception;

class MessagingShippingException extends Exception
{
    /**
     * Create a new messaging shipping exception instance.
     */
    public function __construct(string $message = '', int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->error('[Messaging Shipping] Exception: ' . $this->getMessage(), [
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request = null)
    {
        if ($request && $request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ], 422);
        }

        return null;
    }
}
