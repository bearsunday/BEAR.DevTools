<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Resource\ResourceObject;
use Madapaja\TwigModule\Annotation\TwigPaths;
use Ray\Aop\ReflectionClass;
use Ray\Aop\WeavedInterface;
use Ray\Di\Di\Named;

use function file_exists;
use function get_class;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

final class TemplateLocator
{
//    private array $twigPaths;

    /**
     * @param array<string> $twigPaths
     * @param array<string> $qiqPaths
     */
    public function __construct(
        private AbstractAppMeta $meta,
        #[TwigPaths('twigPaths')] private array $twigPaths = [],
        #[Named('qiq_paths')] private array $qiqPaths = [],
        #[Named('qiq_extension')] private string $qiqExt = '',
    ) {
    }

    public function get(ResourceObject $ro): string
    {
        if ($this->qiqExt) {
            return $this->getTemplatePath($ro, $this->qiqPaths, $this->qiqExt);
        }

        if ($this->twigPaths) {
            return $this->getTemplatePath($ro, $this->twigPaths, '.html.twig');
        }

        return '';
    }

    /**
     * @param array<string> $paths
     */
    private function getTemplatePath(ResourceObject $ro, array $paths, string $ext): string
    {
        foreach ($paths as $path) {
            $len = strlen(sprintf('%s\Resource', $this->meta->name));
            $roPath = str_replace('\\', '/', substr($this->getClass($ro), $len + 1));
            $maybeFile = sprintf('%s/%s%s', $path, $roPath, $ext);
            if (file_exists($maybeFile)) {
                return $maybeFile;
            }
        }

        return '';
    }

    private function getClass(ResourceObject $ro): string
    {
        if ($ro instanceof WeavedInterface) {
            /** @var \ReflectionClass<ResourceObject> $parentClass */
            $parentClass = (new ReflectionClass($ro))->getParentClass();

            return $parentClass->name;
        }

        return get_class($ro);
    }
}
