<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\ReverseLinkerInterface;
use Override;
use Ray\Aop\ReflectionMethod;
use Ray\Di\Di\Named;

use function implode;
use function is_array;
use function is_string;
use function method_exists;
use function str_starts_with;
use function strlen;
use function substr;
use function ucfirst;
use function uri_template;

final class LinkHeaderRenderer implements RenderInterface
{
    private const HEADER = 'Link';
    private const PAGE_URI_PREFIX = 'page://self';

    public function __construct(
        #[Named('html')]
        private readonly RenderInterface $renderer,
        private readonly ReverseLinkerInterface $reverseLinker,
        private readonly HtmlLinkAuditor $auditor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function render(ResourceObject $ro)
    {
        $links = $this->links($ro);
        $this->appendLinkHeader($ro, $links);
        $ro->view = $this->renderer->render($ro);
        $this->auditor->audit($links, $ro->view);

        return $ro->view;
    }

    /** @param list<LinkHeader> $links */
    private function appendLinkHeader(ResourceObject $ro, array $links): void
    {
        if ($links === []) {
            return;
        }

        $headers = [];
        foreach ($links as $link) {
            $headers[] = $link->headerValue();
        }

        $header = implode(', ', $headers);
        $current = $ro->headers[self::HEADER] ?? null;
        if (is_string($current) && $current !== '') {
            $ro->headers[self::HEADER] = $current . ', ' . $header;

            return;
        }

        $ro->headers[self::HEADER] = $header;
    }

    /** @return list<LinkHeader> */
    private function links(ResourceObject $ro): array
    {
        $method = 'on' . ucfirst($ro->uri->method);
        if (! method_exists($ro, $method)) {
            return [];
        }

        $body = is_array($ro->body) ? $ro->body : [];
        $links = [];
        foreach ((new ReflectionMethod($ro, $method))->getAnnotations() as $annotation) {
            if (! $annotation instanceof Link) {
                continue;
            }

            $href = uri_template($annotation->href, $body);
            $links[] = new LinkHeader(
                $annotation->rel,
                $this->htmlHref($href),
                $annotation->method,
                $annotation->title,
            );
        }

        return $links;
    }

    private function htmlHref(string $href): string
    {
        $href = ($this->reverseLinker)($href, []);
        if (! str_starts_with($href, self::PAGE_URI_PREFIX)) {
            return $href;
        }

        $path = substr($href, strlen(self::PAGE_URI_PREFIX));

        return $path === '' ? '/' : $path;
    }
}
