<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * The gateway could not complete the call (timeout, outage, malformed
 * response). The payment is recorded as failed; the client is not blamed.
 */
class PaymentGatewayException extends RuntimeException {}
