<?php

/**
 * Standardized pagination and chunk loading helper for Stock Bartolo.
 *
 * Supports:
 * - page: 1-based page index (or current_chunk)
 * - limit: rows per page / chunk (default 25, max 250, min 1)
 * - offset: computed as ($page - 1) * $limit
 * - metadata: total, page, per_page, total_pages, has_more, from, to, chunk_size, current_chunk, total_chunks
 */

function sb_get_pagination_params(int $defaultLimit = 25, int $maxLimit = 250): array
{
    // If paginate is explicitly set to '0' or 'false', disable pagination
    $rawPaginate = $_GET['paginate'] ?? $_GET['pagination'] ?? null;
    if ($rawPaginate === '0' || $rawPaginate === 'false' || (isset($_GET['all']) && $_GET['all'] === '1')) {
        return [
            'enabled'       => false,
            'page'          => 1,
            'limit'         => null,
            'offset'        => 0,
            'current_chunk' => 1,
            'chunk_size'    => null,
        ];
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_GET['chunk']) ? (int)$_GET['chunk'] : 1);
    if ($page < 1) {
        $page = 1;
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_GET['per_page']) ? (int)$_GET['per_page'] : (isset($_GET['chunk_size']) ? (int)$_GET['chunk_size'] : $defaultLimit));
    if ($limit < 1) {
        $limit = $defaultLimit;
    }
    if ($limit > $maxLimit) {
        $limit = $maxLimit;
    }

    $offset = ($page - 1) * $limit;

    return [
        'enabled'       => true,
        'page'          => $page,
        'limit'         => $limit,
        'offset'        => $offset,
        'current_chunk' => $page,
        'chunk_size'    => $limit,
    ];
}

function sb_build_pagination_meta(int $total, array $params): array
{
    if (!$params['enabled'] || $params['limit'] === null) {
        return [
            'total'         => $total,
            'page'          => 1,
            'per_page'      => $total,
            'total_pages'   => 1,
            'has_more'      => false,
            'from'          => $total > 0 ? 1 : 0,
            'to'            => $total,
            'chunk_size'    => $total,
            'current_chunk' => 1,
            'total_chunks'  => 1,
        ];
    }

    $limit      = $params['limit'];
    $page       = $params['page'];
    $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
    if ($totalPages < 1) {
        $totalPages = 1;
    }

    $offset     = ($page - 1) * $limit;
    $from       = $total > 0 ? min($offset + 1, $total) : 0;
    $to         = min($offset + $limit, $total);
    $hasMore    = $page < $totalPages;

    return [
        'total'         => $total,
        'page'          => $page,
        'per_page'      => $limit,
        'total_pages'   => $totalPages,
        'has_more'      => $hasMore,
        'from'          => $from,
        'to'            => $to,
        'chunk_size'    => $limit,
        'current_chunk' => $page,
        'total_chunks'  => $totalPages,
    ];
}
