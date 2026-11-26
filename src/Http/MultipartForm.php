<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Types;
use CURLFile;
use Koriym\FileUpload\AbstractFileUpload;

use function escapeshellarg;
use function explode;
use function http_build_query;
use function sprintf;
use function urldecode;

/**
 * The multipart/form-data fields of a query that carries a file
 *
 * @internal
 * @psalm-import-type MultipartFields from Types
 */
final class MultipartForm
{
    /**
     * The fields cURL posts, or null when the query carries no file
     *
     * Values other than files are named as a form names them, `ids[0]` for a list.
     *
     * @param array<string, mixed> $query
     *
     * @return MultipartFields|null
     */
    public function fields(array $query): array|null
    {
        $fields = [];
        $values = [];
        foreach ($query as $name => $value) {
            if ($value instanceof AbstractFileUpload) {
                $fields[$name] = new CURLFile($value->tmpName, $value->type, $value->name);

                continue;
            }

            $values[$name] = $value;
        }

        if ($fields === []) {
            return null;
        }

        $encoded = http_build_query($values);
        foreach ($encoded === '' ? [] : explode('&', $encoded) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $fields[urldecode($name)] = urldecode($value);
        }

        return $fields;
    }

    /**
     * The `-F` arguments of the curl command that posts the fields
     *
     * @param MultipartFields $fields
     */
    public function curlArgs(array $fields): string
    {
        $args = '';
        foreach ($fields as $name => $value) {
            $field = $value instanceof CURLFile
                ? sprintf('%s=@%s;type=%s;filename=%s', $name, $value->getFilename(), $value->getMimeType(), $value->getPostFilename())
                : $name . '=' . $value;
            $args .= ' -F ' . escapeshellarg($field);
        }

        return $args;
    }
}
