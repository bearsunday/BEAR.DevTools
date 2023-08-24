<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\Dev\DevInvoker;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use Ray\Aop\WeavedInterface;
use Ray\Di\Di\Named;
use ReflectionClass;
use ReflectionObject;

use function array_walk_recursive;
use function assert;
use function explode;
use function get_class;
use function highlight_string;
use function is_array;
use function is_object;
use function is_scalar;
use function json_decode;
use function json_encode;
use function number_format;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function setcookie;
use function spl_object_hash;
use function str_contains;
use function str_replace;
use function strpos;
use function substr;
use function time;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

final class HaloRenderer implements RenderInterface
{
    private const HALO_KEY = 'halo';
    private const HALO_COOKIE_KEY = '_bear_sunday_disable_halo';

    private const RESOURCE_LABEL = 'label-success';
    private const BADGE_INTERCEPTORS = '<span class="badge badge-info">Interceptors</span>';
    private const BADGE_PROFILE = '<span class="badge badge-info">Profile</span>';
    private const DIV_WELL = '<div style="padding:10px;">';

    public function __construct(
        #[Named('original')]
        private RenderInterface $renderer,
        private TemplateLocator $templateLocator
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResourceObject $ro)
    {
        if ($this->isDisableHalo()) {
            return $this->renderer->render($ro);
        }

        $originalView =  $this->renderer->render($ro);
        $templatePath = $this->templateLocator->get($ro);
        $haloView = $this->addHalo($originalView, $ro, $templatePath);
        $toolView = $this->addJsDevTools($haloView);
        $ro->view = $toolView;

        return $haloView;
    }

    private function isDisableHalo(): bool
    {
        $disableHalo = (isset($_GET[self::HALO_KEY]) && $_GET[self::HALO_KEY] === '0') || isset($_COOKIE[self::HALO_COOKIE_KEY]);
        if (! empty($_GET[self::HALO_KEY]) && $_GET[self::HALO_KEY] === '1') {
            $disableHalo = false;
            setcookie(self::HALO_COOKIE_KEY, '', time() - 3600);
        }

        if ($disableHalo) {
            setcookie(self::HALO_COOKIE_KEY, '0');
        }

        return $disableHalo;
    }

    private function addJsDevTools(string $body): string
    {
        if (strpos($body, '<head>') === false) {
            return $body;
        }

        $bootstrapCss = '<link href="https://koriym.github.io/BEAR.Package/assets/css/bootstrap.bear.css" rel="stylesheet">' . PHP_EOL .
            '<link href="https://koriym.github.io/BEAR.Package/assets/css/bear.dev.css" rel="stylesheet">' . PHP_EOL;
        $bootstrapCss .= strpos($body, 'glyphicons.css') ? '' : '<link href="https://netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap-glyphicons.css" rel="stylesheet">' . PHP_EOL;
        $tabJs = strpos($body, '/assets/js/bootstrap-tab.js') ? '' : '<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.1/js/bootstrap-tab.js"></script>' . PHP_EOL;
        $bootstrapJs = '<link href="https://netdna.bootstrapcdn.com/bootswatch/3.0.0/united/bootstrap.min.css" rel="stylesheet">';
        $toolLoad = <<<EOT
<!-- BEAR.Sunday dev tools -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
{$bootstrapCss}{$tabJs}{$bootstrapJs}
<!-- /BEAR.Sunday dev tools -->
EOT;

        return str_replace('</head>', "\n{$toolLoad}</head>", $body);
    }

    private function addHalo(string $body, ResourceObject $ro, string $templateFile): string
    {
        return $this->addResourceMetaInfo($ro, self::RESOURCE_LABEL, $templateFile, $body);
    }

    private function addResourceMetaInfo(
        ResourceObject $ro,
        string $labelColor,
        string $templateFile,
        string $body
    ): string {
        $resourceName = $ro->uri;
        // code editor
        $ref = new ReflectionObject($ro);
        $codeFile = $ro instanceof WeavedInterface && $ref->getParentClass() ? $ref->getParentClass()->getFileName() : $ref->getFileName();
//        $codeFile = $this->makeRelativePath($codeFile);
        $var = $this->getStatus($ro->body);
        $resourceKey = spl_object_hash($ro);
        $escapedBody = (string) preg_replace('/<!-- BEAR\.Sunday dev tool load -->.*BEAR\.Sunday dev tool load -->/', '', $body);

        $resourceBody = (string) preg_replace_callback(
            '/<!-- resource(.*?)resource_tab_end -->/s',
            static function ($matches) {
                $uri = substr(explode(' ', $matches[1])[0], 1);
                preg_match('/ <!-- resource_body_start -->(.*?)<!-- resource_body_end -->/s', $matches[1], $resourceBodyMatch);

                return "<!-- resource:$uri -->\n{$resourceBodyMatch[1]}<!-- /resource:$uri -->";
            },
            $escapedBody
        );
        $resourceBodyHtml = highlight_string($resourceBody, true);
        $info = $this->getResourceInfo($ro);
        $editTemplate = $templateFile ? <<<EOT
<a href="phpstorm://open?file={$templateFile}"><span class="glyphicon glyphicon-file"
        rel="tooltip" title="Edit {$templateFile}"></span></a>
EOT : '<span style="color: lightgray" class="glyphicon glyphicon-file"></span>';
        $result = <<<EOT
<!-- resource:{$resourceName} -->
<div class="bearsunday">
<div class="toolbar">
    <span class="label {$labelColor}">{$resourceName}</span>
    <a data-toggle="tab" href="#{$resourceKey}_body" class="home"><span class="glyphicon glyphicon-home"
    rel="tooltip" title="Home"></span></a>
    <a data-toggle="tab" href="#{$resourceKey}_var"><span class="glyphicon glyphicon-zoom-in" rel="tooltip"
    title="Status"></span></a>
    <a data-toggle="tab" href="#{$resourceKey}_html"><span class="glyphicon glyphicon-font" rel="tooltip"
    title="View"></span></a>
    <a data-toggle="tab" href="#{$resourceKey}_info"><span class="glyphicon glyphicon-info-sign" rel="tooltip"
    title="Info"></span></a>
    <span class="edit">
        <a href="phpstorm://open?file={$codeFile}"><span class="glyphicon glyphicon-edit"
        rel="tooltip" title="Edit {$codeFile}"></span></a>
        {$editTemplate}
    </span>
</div>

<div class="tab-content frame">
    <div id="{$resourceKey}_body" class="tab-pane fade active in">
    <!-- resource_body_start -->
EOT;
        $result = str_contains($body, '<body>') ? str_replace('<body>', '<body>' . $result, $body) : $result . $body;
        $label = <<<EOT
<!-- resource_body_end -->
<!-- /resource:'{$resourceName}' -->
<!-- resource_tab_start -->
    </div>
    <div id="{$resourceKey}_var" class="tab-pane">
        <div class="tab-wrap">
            <span class="badge badge-info">State</span><br>{$var}
        </div>
    </div>
    <div id="{$resourceKey}_html" class="tab-pane">
        <div class="tab-wrap">
            <span class="badge badge-info">View</span><br>{$resourceBodyHtml}
        </div>
    </div>
    <div id="{$resourceKey}_info" class="tab-pane">
        <div class="tab-wrap">{$info}</div>
    </div>
</div>
</div>
<!-- resource_tab_end -->
EOT;

        return $result . $label;
    }

    private function getStatus(mixed $body): string
    {
        if (is_scalar($body)) {
            return (string) $body;
        }

        if (! is_array($body)) {
            return '-';
        }

        array_walk_recursive(
            $body,
            static function (mixed &$value): void {
                if ($value instanceof Request) {
                    $value = '[' . $value->toUri() . ']';
                }

                if ($value instanceof ResourceObject) {
                    $value = $value->body;
                }

                if (! is_object($value)) {
                    return;
                }

                $value = '(object) ' . get_class($value);
            }
        );

        return '<pre>' . json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
    }

    private function getResourceInfo(ResourceObject $ro): string
    {
        $info = '';
        $info .= $this->getInterceptorInfo($ro);
        $info .= $this->getProfileInfo($ro);

        return $info;
    }

    private function getInterceptorInfo(ResourceObject $ro): string
    {
        $result = self::BADGE_INTERCEPTORS . self::DIV_WELL;
        if (! isset($ro->headers[DevInvoker::HEADER_INTERCEPTORS])) {
            return $result . 'n/a</div>';
        }

        $interceptors = json_decode($ro->headers[DevInvoker::HEADER_INTERCEPTORS], true);
        assert(is_array($interceptors));
        unset($ro->headers[DevInvoker::HEADER_INTERCEPTORS]);
        $onGetInterceptors = $interceptors['onGet'] ?: [];
        foreach ($onGetInterceptors as $interceptor) {
            $interceptorFile = (new ReflectionClass($interceptor))->getFileName();
            $result .= <<<EOT
<li><a href="phpstorm://open?file={$interceptorFile}">{$interceptor}</a>
EOT;
        }

        $result .= '</div>';

        return $result;
    }

    private function getProfileInfo(ResourceObject $ro): string
    {
        // memory, time
        $html = self::BADGE_PROFILE . self::DIV_WELL;
        $time = isset($ro->headers[DevInvoker::HEADER_EXECUTION_TIME]) ?
            number_format((float) $ro->headers[DevInvoker::HEADER_EXECUTION_TIME], 3) : 0;

        $memory = isset($ro->headers[DevInvoker::HEADER_MEMORY_USAGE]) ?
            number_format((float) $ro->headers[DevInvoker::HEADER_MEMORY_USAGE]) : 0;

        $html .= <<<EOT
    <li><span class="glyphicon glyphicon-time"></span>  {$time} sec
    <li><span class="glyphicon glyphicon-signal"></span> {$memory} bytes
EOT;
        // profile id
        if (isset($ro->headers[DevInvoker::HEADER_PROFILE_ID])) {
            $profileId = $ro->headers[DevInvoker::HEADER_PROFILE_ID];
            $html .= <<<EOT
    <li><span title="XHProf">XH</span> <a href="/xhprof_html/index.php?run={$profileId}&source=resource">{$profileId}</a>
EOT;
        }

        $html .= '</ul>';

        return $html . '</div>';
    }
}
