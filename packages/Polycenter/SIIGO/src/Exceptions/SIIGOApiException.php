<?php

namespace Polycenter\SIIGO\Exceptions;

use Exception;

class SIIGOApiException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ], 422);
        }

        return redirect()->back()->withErrors([
            'siigo' => $this->getMessage()
        ]);
    }
}
