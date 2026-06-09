<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use Override;

final class SemanticAnchorRenderer implements RenderInterface
{
    #[Override]
    public function render(ResourceObject $ro): string
    {
        unset($ro);

        return '<a href="/next" class="goNext">Next</a>';
    }
}
