<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

if (!function_exists('json_input')) {
    /**
     * Read the raw JSON request body as an array.
     *
     * Unlike Api::body(), values are NOT html-escaped, so names such as
     * "Tom's Shirt" or passwords containing & or < are stored exactly as sent.
     * Output escaping is the frontend's job (React escapes by default).
     */
    function json_input(): array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            return [];
        }
        array_walk_recursive($data, function (&$v) {
            if (is_string($v)) {
                $v = trim($v);
            }
        });
        return $data;
    }
}
