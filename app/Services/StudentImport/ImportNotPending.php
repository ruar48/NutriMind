<?php

namespace App\Services\StudentImport;

use RuntimeException;

/**
 * Thrown when an import is being committed by another request (e.g. a double submit) or its file is already gone.
 */
class ImportNotPending extends RuntimeException {}
