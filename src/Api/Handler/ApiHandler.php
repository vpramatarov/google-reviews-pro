<?php

namespace GRP\Api\Handler;

interface ApiHandler
{
    public function fetch(): \WP_Error|array;

    public function supports(string $source): bool;
}