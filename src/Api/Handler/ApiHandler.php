<?php

namespace GRP\Api\Handler;

interface ApiHandler
{
    public function fetch(string $id): \WP_Error|array;

    public function fetch_business_info(string $query): \WP_Error|array;

    public function fetch_business_info_by_place_id(string $place_id): \WP_Error|array;

    public function supports(string $source): bool;
}