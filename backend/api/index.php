<?php

/**
 * Serverless entrypoint for Vercel / Cloud functions.
 * Forwards requests to Laravel's public front controller.
 */
require __DIR__ . '/../public/index.php';

