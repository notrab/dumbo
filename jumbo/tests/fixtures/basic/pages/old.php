<?php

return new Dumbo\Jumbo\Page(render: fn($context) => $context->redirect("/about", 301));
