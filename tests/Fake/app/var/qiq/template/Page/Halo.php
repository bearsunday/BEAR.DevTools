<?php

use Qiq\Template;

assert($this instanceof Template);
$this->setLayout('layout/base');
?>
<h1>Greeting</h1>
<p>{{h $this->greeting }}</p>

{{= $this->ja }}

