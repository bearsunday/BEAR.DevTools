<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HttpRequestException;
use BEAR\Dev\Types;
use CURLFile;
use Koriym\FileUpload\AbstractFileUpload;
use Koriym\FileUpload\ErrorFileUpload;

use function explode;
use function http_build_query;
use function is_array;
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
     * Each field is named as a form names it, `images[0]` for a file in a list, so PHP reads the files into `$_FILES`
     * and the other values into `$_POST` with the structure of the query.
     *
     * @param array<string, mixed> $query
     *
     * @return MultipartFields|null
     *
     * @throws HttpRequestException For an upload that failed, which has no file to send.
     */
    public function fields(array $query): array|null
    {
        $fields = $this->files($query, '');
        if ($fields === []) {
            return null;
        }

        $encoded = http_build_query($this->withoutFiles($query));
        foreach ($encoded === '' ? [] : explode('&', $encoded) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $fields[urldecode($name)] = urldecode($value);
        }

        return $fields;
    }

    /**
     * The curl options that post the fields, each as an option and its value
     *
     * A value is sent with `--form-string`, since `-F` would read a file named by a value that starts with `@` or `<`.
     *
     * @param MultipartFields $fields
     *
     * @return list<array{string, string}>
     */
    public function curlArgs(array $fields): array
    {
        $args = [];
        foreach ($fields as $name => $value) {
            $args[] = $value instanceof CURLFile
                ? ['-F', sprintf('%s=@%s;type=%s;filename=%s', $name, $value->getFilename(), $value->getMimeType(), $value->getPostFilename())]
                : ['--form-string', $name . '=' . $value];
        }

        return $args;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, CURLFile>
     */
    private function files(array $values, string $prefix): array
    {
        $files = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : sprintf('%s[%s]', $prefix, $key);
            if ($value instanceof ErrorFileUpload) {
                throw new HttpRequestException(sprintf('The upload "%s" failed and has no file to send: %s', $name, (string) $value->message));
            }

            if ($value instanceof AbstractFileUpload) {
                $files[$name] = new CURLFile($value->tmpName, $value->type, $value->name);

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $files += $this->files($value, $name);
        }

        return $files;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function withoutFiles(array $values): array
    {
        $rest = [];
        foreach ($values as $key => $value) {
            if ($value instanceof AbstractFileUpload) {
                continue;
            }

            $rest[$key] = is_array($value) ? $this->withoutFiles($value) : $value;
        }

        return $rest;
    }
}
