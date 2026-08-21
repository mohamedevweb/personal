<?php

namespace App\Services\Discovery;

/**
 * Backward-compatible name for callers that still resolve the original service.
 * New discovery code depends on OutlierScore directly.
 */
class PostPerformance extends OutlierScore {}
