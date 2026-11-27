<?php

namespace App\Exceptions;

use DomainException;

/** The valid batches of a product cannot cover the requested quantity (FEFO). */
class InsufficientStockException extends DomainException {}
