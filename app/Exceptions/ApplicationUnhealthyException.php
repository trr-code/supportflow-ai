<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class ApplicationUnhealthyException extends RuntimeException implements ShouldntReport {}
